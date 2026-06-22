#!/usr/bin/env python3
"""
Feedback automático + analítica para Moodle, con IA local (Ollama).

Lee, vía la API REST de Moodle (web services), los alumnos de un curso y su progreso
(módulos completados), y para cada uno genera feedback personalizado con un LLM local.
Después arma un informe para el docente, detectando alumnos en riesgo de abandono.

Uso:
    export MOODLE_WSTOKEN="<tu_token>"
    python3 05_feedback_analitica.py

No requiere dependencias externas (usa solo la librería estándar de Python).
"""

import os
import sys
import json
import urllib.request
import urllib.parse

# ----------------------- Configuración -----------------------
MOODLE_URL = os.environ.get("MOODLE_URL", "http://localhost:8000")
WSTOKEN    = os.environ.get("MOODLE_WSTOKEN", "")
COURSE_ID  = int(os.environ.get("MOODLE_COURSEID", "2"))
OLLAMA_URL = os.environ.get("OLLAMA_URL", "http://localhost:11434")
MODEL      = os.environ.get("OLLAMA_MODEL", "llama3")

if not WSTOKEN:
    sys.exit("Falta el token. Hacé: export MOODLE_WSTOKEN=\"<tu_token>\"")


# ----------------------- Cliente API Moodle -----------------------
def moodle(wsfunction, **params):
    """Llama a una función de web service de Moodle y devuelve el JSON."""
    params.update({
        "wstoken": WSTOKEN,
        "wsfunction": wsfunction,
        "moodlewsrestformat": "json",
    })
    url = f"{MOODLE_URL}/webservice/rest/server.php"
    data = urllib.parse.urlencode(params, doseq=True).encode()
    with urllib.request.urlopen(url, data=data, timeout=30) as r:
        out = json.loads(r.read().decode())
    if isinstance(out, dict) and out.get("exception"):
        raise RuntimeError(f"{wsfunction}: {out.get('message')}")
    return out


# ----------------------- Cliente Ollama -----------------------
def ia(prompt, system="Sos un tutor educativo. Respondé en español rioplatense, claro y breve."):
    """Genera texto con el modelo local de Ollama."""
    body = json.dumps({
        "model": MODEL,
        "prompt": prompt,
        "system": system,
        "stream": False,
    }).encode()
    req = urllib.request.Request(f"{OLLAMA_URL}/api/generate", data=body,
                                 headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=120) as r:
        return json.loads(r.read().decode())["response"].strip()


# ----------------------- Lógica -----------------------
def estado(pct):
    if pct >= 100: return "🟢 Al día"
    if pct > 0:    return "🟡 A medias"
    return "🔴 En riesgo"


def main():
    print(f"Conectando a Moodle ({MOODLE_URL}) ...\n")

    # 1. Alumnos del curso (solo estudiantes)
    usuarios = moodle("core_enrol_get_enrolled_users", courseid=COURSE_ID)
    alumnos = [u for u in usuarios
               if any(r["shortname"] == "student" for r in u.get("roles", []))]

    reporte = []
    for u in alumnos:
        # 2. Progreso: actividades completadas
        comp = moodle("core_completion_get_activities_completion_status",
                      courseid=COURSE_ID, userid=u["id"])
        actividades = comp.get("statuses", [])
        total = len(actividades)
        hechas = sum(1 for a in actividades if a.get("state") == 1)
        pct = round(hechas / total * 100) if total else 0

        # 3. Feedback personalizado con IA
        prompt = (
            f"Un alumno del curso 'Monitoreo ambiental en minería' (capacitación profesional) "
            f"completó {hechas} de {total} módulos ({pct}%). "
            f"Escribí un mensaje de feedback breve (2-3 frases), motivador y concreto, "
            f"dirigido directamente al alumno. Si va atrasado, animalo con un próximo paso claro. "
            f"REGLAS: no inventes nombres de módulos, cursos ni contenidos que no se mencionan; "
            f"hablá en términos generales (ej. 'el próximo módulo'). Es un adulto en formación "
            f"profesional, no menciones familia ni escuela."
        )
        feedback = ia(prompt)

        reporte.append({
            "nombre": u["fullname"], "hechas": hechas, "total": total,
            "pct": pct, "estado": estado(pct), "feedback": feedback,
        })
        print(f"✓ {u['fullname']}: {hechas}/{total} ({pct}%) {estado(pct)}")

    # 4. Informe para el docente (resumen con IA)
    resumen_datos = "\n".join(
        f"- {r['nombre']}: {r['hechas']}/{r['total']} ({r['pct']}%) — {r['estado']}"
        for r in reporte)
    en_riesgo = [r["nombre"] for r in reporte if r["pct"] == 0]
    prompt_doc = (
        f"Sos analista educativo de una capacitación profesional para adultos. "
        f"Estos son los avances de los alumnos:\n{resumen_datos}\n\n"
        f"Escribí un informe breve (4-5 frases) para el docente: estado general del curso, "
        f"quiénes necesitan atención y una recomendación accionable. "
        f"REGLAS: usá solo los datos provistos, no inventes notas ni causas; "
        f"es formación profesional de adultos (no menciones familia ni escuela). En español."
    )
    print("\nGenerando informe para el docente...\n")
    informe = ia(prompt_doc, system="Sos analista educativo. Respondé en español, claro y profesional.")

    # 5. Salida: consola + archivo Markdown
    md = ["# Informe de seguimiento — Monitoreo ambiental en minería\n",
          "## Resumen por alumno\n",
          "| Alumno | Progreso | Estado |", "|---|---|---|"]
    for r in reporte:
        md.append(f"| {r['nombre']} | {r['hechas']}/{r['total']} ({r['pct']}%) | {r['estado']} |")
    md.append("\n## Feedback personalizado (generado por IA)\n")
    for r in reporte:
        md.append(f"**{r['nombre']}** — {r['estado']}\n\n> {r['feedback']}\n")
    md.append("## Informe para el docente (generado por IA)\n")
    md.append(informe + "\n")
    if en_riesgo:
        md.append(f"\n⚠️ **Atención prioritaria:** {', '.join(en_riesgo)}")

    salida = "\n".join(md)
    with open("informe_alumnos.md", "w", encoding="utf-8") as f:
        f.write(salida)

    print("=" * 60)
    print(salida)
    print("=" * 60)
    print("\n✅ Informe guardado en: informe_alumnos.md")


if __name__ == "__main__":
    main()
