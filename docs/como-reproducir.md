# Cómo reproducir el entorno (paso a paso)

Guía para levantar todo desde cero en una Mac (Apple Silicon). Probado en macOS + Docker
Desktop + Ollama.

## Requisitos previos

- **Docker Desktop** (motor de contenedores). Instalación: `brew install --cask docker`,
  después abrir la app y dejar el motor corriendo ("Engine running").
- **Ollama** con modelos descargados:
  ```bash
  ollama pull llama3
  ollama pull nomic-embed-text   # para el RAG futuro
  ```

> ⚠️ **Importante:** trabajar en una ruta **sin espacios** (ej. `~/moodle-dev/`). Los
> scripts de `moodle-docker` se rompen con espacios en la ruta.

## 1. Entorno Moodle con Docker

```bash
mkdir -p ~/moodle-dev && cd ~/moodle-dev
git clone https://github.com/moodlehq/moodle-docker.git
cd moodle-docker
git clone --depth 1 -b MOODLE_500_STABLE https://github.com/moodle/moodle.git moodle

export MOODLE_DOCKER_WWWROOT="$PWD/moodle"
export MOODLE_DOCKER_DB=pgsql
export MOODLE_DOCKER_WEB_PORT=8000
cp config.docker-template.php "$MOODLE_DOCKER_WWWROOT/config.php"

bin/moodle-docker-compose up -d

# Instalar la base de datos + usuario admin
bin/moodle-docker-compose exec webserver php admin/cli/install_database.php \
  --agree-license --adminuser="admin" --adminpass="Tutoria2026!" \
  --adminemail="admin@example.com" \
  --fullname="Tutor IA - Curso Demo" --shortname="tutoria" \
  --summary="Curso demo con tutor de IA"
```

Moodle queda en **http://localhost:8000** (usuario `admin`, contraseña `Tutoria2026!`).

## 2. Cargar el curso demo

Copiar el material y correr los scripts de provisioning:

```bash
# desde la raíz de este repo:
cp curso/*.md ~/moodle-dev/moodle-docker/moodle/_provision/   # crear la carpeta si no existe
cp scripts/01_provision_curso.php ~/moodle-dev/moodle-docker/moodle/
cp scripts/02_cargar_contenido.php ~/moodle-dev/moodle-docker/moodle/

cd ~/moodle-dev/moodle-docker
bin/moodle-docker-compose exec webserver php 01_provision_curso.php
bin/moodle-docker-compose exec webserver php 02_cargar_contenido.php
```

## 3. Conectar Ollama (subsistema de IA)

En la web de Moodle:

1. **Site administration → General → AI → AI providers → Create a new provider instance**
   - Plugin: **Ollama API provider**
   - Name: `Ollama local`
   - API endpoint: `http://host.docker.internal:11434`
   - Crear y **habilitar** (toggle Enabled).
2. En **Settings** del provider, para cada acción (Generate / Summarise / Explain):
   - Model: **Custom** → `llama3`
3. **Site administration → AI → AI placements** → habilitar
   *Course assistance* y *Text editor*.

### Destrabar la seguridad para Ollama (solo dev local)

```bash
cd ~/moodle-dev/moodle-docker
bin/moodle-docker-compose exec webserver php admin/cli/cfg.php --name=curlsecurityblockedhosts --set=""
bin/moodle-docker-compose exec webserver php admin/cli/cfg.php --name=curlsecurityallowedport --set=$'80\n443\n11434'
bin/moodle-docker-compose exec webserver php admin/cli/purge_caches.php
```

(Ver `decisiones-tecnicas.md` para el porqué.)

### Respuestas en español (opcional)

```bash
cp scripts/03_instrucciones_espanol.php ~/moodle-dev/moodle-docker/moodle/
cd ~/moodle-dev/moodle-docker
bin/moodle-docker-compose exec webserver php 03_instrucciones_espanol.php
```

## 4. Chatbot conversacional (block_ollama_chat)

```bash
cd ~/moodle-dev/moodle-docker/moodle/blocks
git clone https://github.com/ragcon-ai/moodle-block_ollama_chat.git ollama_chat
cd ~/moodle-dev/moodle-docker
bin/moodle-docker-compose exec webserver php admin/cli/upgrade.php --non-interactive

# Configurar para usar Ollama (modo chat)
bin/moodle-docker-compose exec webserver php admin/cli/cfg.php --component=block_ollama_chat --name=apiendpoint --set="http://host.docker.internal:11434"
bin/moodle-docker-compose exec webserver php admin/cli/cfg.php --component=block_ollama_chat --name=model --set="llama3"
bin/moodle-docker-compose exec webserver php admin/cli/cfg.php --component=block_ollama_chat --name=apikey --set="ollama"

# Cargar rol del tutor + material del curso como "fuente de verdad"
cp scripts/04_config_chatbot.php ~/moodle-dev/moodle-docker/moodle/
bin/moodle-docker-compose exec webserver php 04_config_chatbot.php
```

Luego, en el curso: **Edit mode → Add a block → Ollama Chat Block**.

> El modo debe ser **chat** (default), no *assistant*: Ollama soporta `/v1/chat/completions`
> pero no la API de Assistants de OpenAI. Ver `decisiones-tecnicas.md`.

## 5. Probar

- **Asistente (resumir/explicar):** entrar a un módulo → **✨ AI features → Explicar / Resumir**.
- **Chatbot:** en el curso, escribir una pregunta en el bloque **Ollama Chat**.

## 6. Feedback automático + analítica (Python + API REST)

Datos de prueba (alumnos con progreso variado) y habilitar la API REST:

```bash
cp scripts/05_feedback_analitica.py ~/moodle-dev/moodle-docker/moodle/  # opcional, corre desde el repo
cd ~/moodle-dev/moodle-docker
# (scripts _alumnos.php y _webservice.php crean alumnos, progreso, servicio y token)
bin/moodle-docker-compose exec webserver php _alumnos.php
bin/moodle-docker-compose exec webserver php _webservice.php   # imprime TOKEN=...
```

Correr el pipeline (Python, sin dependencias externas):

```bash
cd <ruta-del-repo>/scripts
export MOODLE_WSTOKEN="<el token del paso anterior>"
export MOODLE_URL="http://localhost:8000"
export MOODLE_COURSEID="2"
python3 05_feedback_analitica.py
```

Genera feedback por alumno + informe para el docente (ver `informe-ejemplo.md`).

## Comandos útiles del día a día

```bash
cd ~/moodle-dev/moodle-docker
export MOODLE_DOCKER_WWWROOT="$PWD/moodle"; export MOODLE_DOCKER_DB=pgsql; export MOODLE_DOCKER_WEB_PORT=8000

bin/moodle-docker-compose stop    # apagar sin borrar datos
bin/moodle-docker-compose start   # volver a prender
bin/moodle-docker-compose down    # apagar y BORRAR contenedores (los datos se pierden)
bin/moodle-docker-compose logs --tail=50 webserver   # ver logs
```
