# Idioma español y acceso para revisión (demo)

Ajustes aplicados para dejar el MVP **listo para que alguien externo lo revise** (interfaz
100% en español, sin "modo desarrollador", y accesible desde otra computadora).

---

## 1. Interfaz en español

### 1a. Paquete de idioma de Moodle

Instala el idioma español y déjalo por defecto:

```bash
cd ~/moodle-dev/moodle-docker

# Descargar el langpack es 5.0 dentro de moodledata
bin/moodle-docker-compose exec webserver bash -c '
  mkdir -p /var/www/moodledata/lang && cd /var/www/moodledata/lang &&
  curl -sL -o es.zip "https://download.moodle.org/download.php/direct/langpack/5.0/es.zip" &&
  unzip -o -q es.zip && rm -f es.zip'

# Fijarlo por defecto + limpiar caché
bin/moodle-docker-compose exec webserver php admin/cli/cfg.php --name=lang --set=es
bin/moodle-docker-compose exec webserver php admin/cli/purge_caches.php
```

### 1b. Textos del chatbot en español

El plugin `block_ollama_chat` solo trae inglés y alemán, así que el bloque se veía como
**"Ollama Chat" / "Ask a question..."**. Se agregó un archivo de idioma español propio
(versionado en este repo) que lo deja como **"Tutor IA" / "Escribí tu pregunta..."**:

```bash
# Copiar el español del plugin (incluido en este repo) y purgar caché
cp scripts/personalizaciones/block_ollama_chat/lang/es/block_ollama_chat.php \
   ~/moodle-dev/moodle-docker/moodle/blocks/ollama_chat/lang/es/block_ollama_chat.php
cd ~/moodle-dev/moodle-docker
bin/moodle-docker-compose exec webserver php admin/cli/purge_caches.php
```

> El título del bloque sale del string `ollama_chat` (no de la config de la instancia,
> que está vacía), por eso alcanza con el archivo de idioma — sin tocar la base de datos.

---

## 2. Apagar el "modo desarrollador" (para que se vea profesional)

El template de `moodle-docker` deja el debug en modo DEVELOPER, que muestra un panel negro
de *performance* y mensajes de error al pie. Para una demo hay que apagarlo en
`moodle/config.php`:

```php
// Debug DESACTIVADO para revisión (el template lo deja en DEVELOPER).
$CFG->debug = 0;            // DEBUG_NONE
$CFG->debugdisplay = 0;
$CFG->debugstringids = 0;
$CFG->perfdebug = 0;
$CFG->debugpageinfo = 0;
```

(Para volver a desarrollar, restaurar los valores originales — `E_ALL`, `1`, `15`, etc.)

---

## 3. Acceso desde otra computadora (revisión remota)

Todo corre en local (Moodle en Docker + Ollama). Para que un tercero entre se expone el
puerto 8000 con un **túnel de VS Code** (panel **PORTS** → *Forward a Port* → `8000`).

> ⚠️ **La visibilidad del puerto debe quedar en `Public`.** Si queda en `Private`, el túnel
> pide iniciar sesión en GitHub y el revisor no puede entrar.
>
> ℹ️ La primera vez, el visitante ve un aviso azul de Microsoft ("Está a punto de conectarse
> a un túnel de desarrollador...") → solo tiene que pulsar **Continuar** (aparece una vez por túnel).

### wwwroot dinámico para el túnel

Por defecto Moodle arma sus URLs apuntando a `localhost:8000`, que en la máquina del revisor
**no existe** (se rompen links e imágenes). Se agregó a `moodle/config.php`, **antes** del
branch de gitpod, un bloque que detecta el host del túnel y ajusta `wwwroot`:

```php
// Túnel HTTPS público (VS Code dev tunnels, Cloudflare, ngrok, etc.): respetar el host
// externo para que Moodle arme URLs absolutas correctas para revisores remotos.
$tunnelhost = !empty($_SERVER['HTTP_X_FORWARDED_HOST'])
    ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'])[0])
    : (!empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
$tunnelislocal = ($tunnelhost === '')
    || strpos($tunnelhost, 'localhost') !== false
    || strpos($tunnelhost, '127.0.0.1') !== false;
if (!$tunnelislocal) {
    // Se llega a través de un túnel HTTPS público.
    $CFG->wwwroot  = 'https://' . $tunnelhost;
    $CFG->sslproxy = true;
} else if (strpos($_SERVER['HTTP_HOST'], '.gitpod.io') !== false) {
    // ... (branch original de gitpod) ...
}
```

Así, accediendo por `localhost` sigue funcionando igual, y accediendo por el túnel Moodle
usa la URL pública.

---

## Checklist para el día de la revisión

El link solo vive mientras la Mac esté despierta y todo corriendo. Antes de avisar, **abrir
el link uno mismo**: si lo ves vos, lo ven ellos.

- [ ] Mac prendida y enchufada (sin suspensión)
- [ ] Docker corriendo (`bin/moodle-docker-compose start`)
- [ ] Ollama corriendo (`ollama list` responde)
- [ ] VS Code abierto, puerto 8000 reenviado y en **Public**
