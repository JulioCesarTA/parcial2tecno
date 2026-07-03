<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 0; }

    .encabezado { border-bottom: 3px solid #1f6feb; padding-bottom: 10px; margin-bottom: 18px; }
    .encabezado h1 { color: #1f6feb; font-size: 20px; margin: 0 0 4px; }
    .encabezado .sub { color: #445; font-size: 13px; }
    .encabezado .meta { color: #666; font-size: 10px; margin-top: 4px; }
    .marca { float: right; text-align: right; color: #1f6feb; font-weight: bold; font-size: 14px; }

    .seccion-titulo { font-size: 13px; color: #1f2937; margin: 18px 0 8px; padding-bottom: 3px; border-bottom: 1px solid #e5e7eb; }

    /* Documento destacado (Nº factura / guía / código de cotización) */
    .documento-num { font-size: 22px; font-weight: bold; color: #1f2937; margin: 2px 0 10px; }

    /* Tarjetas de resumen (KPIs) */
    .kpis { width: 100%; margin-bottom: 4px; }
    .kpis td { padding: 4px; }
    .kpi { background: #f4f6f8; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px; text-align: center; }
    .kpi .valor { font-size: 16px; font-weight: bold; color: #1f6feb; }
    .kpi .etiqueta { font-size: 9px; color: #666; text-transform: uppercase; margin-top: 2px; }

    /* Ficha de datos (grilla de pares etiqueta/valor) */
    .ficha { width: 100%; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; }
    .ficha td { padding: 5px 8px; vertical-align: top; width: 25%; }
    .ficha .etiqueta { color: #666; font-size: 9px; text-transform: uppercase; }
    .ficha .valor { font-size: 12px; font-weight: bold; color: #1f2937; }

    /* Caja de total grande, destacada */
    .total-caja { background: #1f6feb; color: #fff; border-radius: 8px; padding: 12px 16px; text-align: right; margin: 12px 0 16px; }
    .total-caja .etiqueta { font-size: 10px; text-transform: uppercase; opacity: .85; }
    .total-caja .valor { font-size: 24px; font-weight: bold; }

    /* Tablas */
    table.datos { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.datos th { background: #1f6feb; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; }
    table.datos td { padding: 5px 8px; border-bottom: 1px solid #eee; }
    table.datos tr:nth-child(even) td { background: #f9fafb; }
    .num { text-align: right; }
    .vacio { color: #999; font-style: italic; padding: 8px; }

    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
    .badge.ok { background: #d7f5e3; color: #187a48; }
    .badge.bajo, .badge.error { background: #fbe0e4; color: #b32338; }
    .badge.aviso { background: #fdecd2; color: #a3630a; }
    .badge.info { background: #dbe8fd; color: #1f5fc4; }
    .badge.gris { background: #e9edf2; color: #445; }

    /* Línea de tiempo (historial de estados) */
    .timeline td { padding: 6px 8px; border-bottom: 1px dashed #e5e7eb; }
    .timeline .punto { color: #1f6feb; font-weight: bold; }

    .nota { color: #666; font-size: 10px; margin-top: 10px; }
    .pie { position: fixed; bottom: -10px; left: 0; right: 0; text-align: center; color: #999; font-size: 9px; }
</style>
