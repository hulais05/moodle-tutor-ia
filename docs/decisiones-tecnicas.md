# Decisiones técnicas y problemas resueltos

> Este documento registra los problemas reales que aparecieron al integrar IA dentro de
> Moodle y cómo se resolvieron. Es la diferencia entre "sé de Moodle y sé de IA" y "ya hice
> funcionar IA *dentro* de Moodle".

## 1. Arquitectura del subsistema de IA de Moodle 5.0

Moodle 5.0 separa la IA en tres piezas:

- **Provider** (proveedor): el conector al modelo (OpenAI, Azure, **Ollama**). Define cómo
  se habla con el servicio externo.
- **Placement** (emplazamiento): *dónde* aparece la IA para el usuario. Se usaron dos:
  - *Course assistance* → asistente dentro de las páginas de actividad (resumir/explicar).
  - *Text editor* → acciones de IA dentro del editor de texto.
- **Manager**: el controlador central que recibe la petición, aplica políticas y registra.

Las acciones (generate / summarise / explain) se configuran **por proveedor**, y cada una
puede usar un **modelo distinto**.

## 2. Por qué un modelo local (Ollama) y no la nube

- **Costo:** $0. Sin API key, sin tarjeta.
- **Privacidad:** el contenido del curso no sale de la máquina.
- **Didáctico:** demuestra que la arquitectura soporta proveedores open-source, no solo
  los comerciales. (Ver `recomendacion-escala-presupuesto.md` para cuándo conviene cada uno.)

Moodle 5.0 trae un proveedor `aiprovider_ollama` **nativo**: no hizo falta plugin de terceros.

## 3. Problema: el puente entre Docker y Ollama 🌉

Moodle corre dentro de un contenedor Docker; Ollama corre en el host (la Mac).

- Para el contenedor, `localhost` es *él mismo*, no el host.
- Docker Desktop expone el host bajo el nombre **`host.docker.internal`**.
- Por eso el endpoint del proveedor es `http://host.docker.internal:11434`, **no**
  `http://localhost:11434`.

Verificación de que el contenedor llega al host:
```bash
docker compose exec webserver curl -s http://host.docker.internal:11434/api/tags
```

## 4. Problema: protección anti-SSRF de Moodle ("The URL is blocked") 🔒

Al primer intento, la IA devolvió *"Something went wrong"*. El log mostró:

```
Blocked The URL is blocked. ... ai/provider/ollama/classes/abstract_processor.php
```

**Causa:** Moodle tiene una capa de seguridad (`curl_security_helper`) que **bloquea que el
servidor haga peticiones a direcciones internas/privadas** — es una defensa contra ataques
SSRF (que alguien use el servidor para escanear la red interna). Como Ollama está en una
dirección interna, Moodle la bloqueó. Había **dos candados**:

1. **Hosts bloqueados** (`curlsecurityblockedhosts`): incluye rangos privados
   (`127.0.0.0/8`, `192.168.0.0/16`, `10.0.0.0/8`, `172.16.0.0/12`...).
2. **Puertos permitidos** (`curlsecurityallowedport`): por defecto solo `80` y `443`.
   El puerto de Ollama (`11434`) no estaba permitido.

**Solución (entorno de desarrollo local):**
```bash
# Permitir hosts internos (en dev local; en prod se restringe a la IP puntual)
php admin/cli/cfg.php --name=curlsecurityblockedhosts --set=""
# Permitir el puerto de Ollama
php admin/cli/cfg.php --name=curlsecurityallowedport --set=$'80\n443\n11434'
php admin/cli/purge_caches.php
```

> **En producción** esto NO se hace a lo bruto: se deja Ollama en la misma red privada que
> Moodle, o se agrega únicamente la IP/puerto concreto a la allowlist. La protección
> anti-SSRF es deseable; acá solo la ajustamos para un entorno local controlado.

## 5. Problema: el contenido de las páginas se guardaba vacío

Al crear los módulos por código con `add_moduleinfo()`, el contenido quedaba vacío
(`contentformat=0`, texto en blanco). **Causa:** el módulo `page` procesa "archivos
borrador" (draft area) y, al crearse por API sin ese contexto, pisaba el texto.

**Solución:** escribir el contenido directamente en la tabla `page` con formato Markdown
(`FORMAT_MARKDOWN`), salteando el manejo de draft files. Ver `scripts/02_cargar_contenido.php`.

## 6. Problema: la IA respondía en inglés

El `systeminstruction` por defecto de cada acción viene en inglés. La config de las acciones
vive en la columna `actionconfig` (JSON) de la tabla `ai_providers`.

**Solución:** anteponer a cada `systeminstruction` una instrucción de idioma. Ver
`scripts/03_instrucciones_espanol.php`.

## Resumen de aprendizajes

| Tema | Aprendizaje |
|---|---|
| Red en Docker | `host.docker.internal` para llegar del contenedor al host |
| Seguridad Moodle | Existe protección anti-SSRF: hosts bloqueados + puertos permitidos |
| API de contenido | El módulo `page` necesita el contenido escrito de forma directa |
| Config de IA | Las acciones y sus system prompts viven en `ai_providers.actionconfig` |
