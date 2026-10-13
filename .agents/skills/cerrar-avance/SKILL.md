---
name: cerrar-avance
description: "Cierra cada avance: QA, documentación actualizada y commit Git seguro."
---

# Workflow: cerrar avance
1. Verificar entregable y criterios de aceptación.
2. Ejecutar `/verificar-calidad`; NO declarar finalizado lo que falla.
3. Ejecutar `/actualizar-contexto` y asegurar que pendientes, cambios y limitaciones quedaron reflejados.
4. Orquestador ejecuta `/git-checkpoint` sobre archivos propios de este avance.
5. Informar objetivo, RF/RN, rutas, resultados de pruebas, último commit local real o bloqueo, próximo paso.
