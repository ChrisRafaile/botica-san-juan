/**
 * useAdminMenu · Botica San Juan
 * ---------------------------------------------------------------------------
 * Única fuente de verdad de la navegación del panel.
 *
 * Antes vivía incrustada dentro de AdminLayout.vue, lo que obligaba a tocar el
 * layout para añadir una vista y hacía imposible reutilizar la estructura en
 * otros sitios (buscador de comandos, migas de pan, mapa del sitio).
 *
 * Los iconos se importan como componentes, no como cadenas de texto: así
 * TypeScript avisa si un icono no existe y el bundler puede descartar los que
 * no se usan. Antes se resolvían con un mapa de strings, que fallaba en
 * silencio devolviendo un icono por defecto.
 */

import type { Component } from 'vue'
import {
  ScanLine,
  LayoutDashboard,
  Pill,
  Users,
  Boxes,
  ReceiptText,
  Truck,
  BarChart3,
  Settings,
} from 'lucide-vue-next'

export interface SubElementoMenu {
  id: string
  etiqueta: string
  ruta: string
  /** Texto para lectores de pantalla cuando la etiqueta no basta por sí sola. */
  descripcion?: string
}

export interface ElementoMenu {
  id: string
  etiqueta: string
  icono: Component
  ruta: string
  /** Agrupación visual dentro de la barra lateral. */
  seccion: 'operacion' | 'gestion' | 'sistema'
  hijos?: SubElementoMenu[]
}

export interface SeccionMenu {
  id: ElementoMenu['seccion']
  etiqueta: string
  elementos: ElementoMenu[]
}

/**
 * Estructura de navegación del panel.
 *
 * El orden no es arbitrario: sigue el flujo real de trabajo de la botica.
 * Primero lo que se usa a diario (operación), luego lo que se revisa de forma
 * periódica (gestión) y al final lo que casi no se toca (sistema).
 */
export const MENU_ADMIN: ElementoMenu[] = [
  {
    id: 'pos',
    etiqueta: 'Punto de venta',
    icono: ScanLine,
    ruta: '/admin/pos',
    seccion: 'operacion',
  },
  {
    id: 'dashboard',
    etiqueta: 'Tablero',
    icono: LayoutDashboard,
    ruta: '/admin/home',
    seccion: 'operacion',
  },
  {
    id: 'productos',
    etiqueta: 'Productos',
    icono: Pill,
    ruta: '/admin/products',
    seccion: 'operacion',
    hijos: [
      { id: 'productos-lista', etiqueta: 'Catálogo', ruta: '/admin/products' },
      { id: 'productos-nuevo', etiqueta: 'Nuevo producto', ruta: '/admin/products/add' },
      { id: 'productos-categorias', etiqueta: 'Categorías', ruta: '/admin/products/categories' },
      { id: 'productos-subcategorias', etiqueta: 'Subcategorías', ruta: '/admin/products/subcategories' },
    ],
  },
  {
    id: 'inventario',
    etiqueta: 'Inventario',
    icono: Boxes,
    ruta: '/admin/inventory',
    seccion: 'operacion',
    hijos: [
      { id: 'inventario-lista', etiqueta: 'Existencias', ruta: '/admin/inventory' },
      { id: 'inventario-stock', etiqueta: 'Control de stock', ruta: '/admin/inventory/stock' },
      {
        id: 'inventario-alertas',
        etiqueta: 'Alertas',
        ruta: '/admin/inventory/alerts',
        descripcion: 'Vencimientos próximos y stock bajo',
      },
      {
        id: 'inventario-conteo',
        etiqueta: 'Conteo por ciclos',
        ruta: '/admin/inventory/conteo',
        descripcion: 'Contar el anaquel y registrar lotes y vencimientos',
      },
    ],
  },
  {
    id: 'facturacion',
    etiqueta: 'Facturación',
    icono: ReceiptText,
    ruta: '/admin/billing',
    seccion: 'operacion',
    hijos: [
      { id: 'facturacion-panel', etiqueta: 'Comprobantes', ruta: '/admin/billing' },
      { id: 'facturacion-sunat', etiqueta: 'Envíos SUNAT', ruta: '/admin/billing/sunat' },
    ],
  },
  {
    id: 'clientes',
    etiqueta: 'Clientes',
    icono: Users,
    ruta: '/admin/clients',
    seccion: 'gestion',
    hijos: [
      { id: 'clientes-lista', etiqueta: 'Ver clientes', ruta: '/admin/clients' },
      { id: 'clientes-nuevo', etiqueta: 'Nuevo cliente', ruta: '/admin/clients/add' },
    ],
  },
  {
    id: 'abastecimiento',
    etiqueta: 'Abastecimiento',
    icono: Truck,
    ruta: '/admin/supply/suppliers',
    seccion: 'gestion',
    hijos: [
      { id: 'abastecimiento-proveedores', etiqueta: 'Proveedores', ruta: '/admin/supply/suppliers' },
      { id: 'abastecimiento-compras', etiqueta: 'Órdenes de compra', ruta: '/admin/supply/purchases' },
      { id: 'abastecimiento-digemid', etiqueta: 'Catálogo DIGEMID', ruta: '/admin/supply/digemid' },
    ],
  },
  {
    id: 'reportes',
    etiqueta: 'Reportes',
    icono: BarChart3,
    ruta: '/admin/reports',
    seccion: 'gestion',
    hijos: [
      { id: 'reportes-ventas', etiqueta: 'Ventas', ruta: '/admin/reports/sales' },
      { id: 'reportes-inventario', etiqueta: 'Inventario', ruta: '/admin/reports/inventory' },
    ],
  },
  {
    id: 'configuracion',
    etiqueta: 'Configuración',
    icono: Settings,
    ruta: '/admin/settings',
    seccion: 'sistema',
  },
]

const ETIQUETAS_SECCION: Record<ElementoMenu['seccion'], string> = {
  operacion: 'Operación diaria',
  gestion: 'Gestión',
  sistema: 'Sistema',
}

/** Menú agrupado por sección, listo para renderizar. */
export function useAdminMenu() {
  const secciones: SeccionMenu[] = (
    ['operacion', 'gestion', 'sistema'] as const
  ).map((id) => ({
    id,
    etiqueta: ETIQUETAS_SECCION[id],
    elementos: MENU_ADMIN.filter((elemento) => elemento.seccion === id),
  }))

  /**
   * Devuelve la ruta de migas de pan para una URL dada.
   * Recorre el menú buscando coincidencia de padre e hijo.
   */
  function migasPara(rutaActual: string): { etiqueta: string; ruta?: string }[] {
    for (const elemento of MENU_ADMIN) {
      const hijo = elemento.hijos?.find((h) => h.ruta === rutaActual)
      if (hijo) {
        return [
          { etiqueta: elemento.etiqueta, ruta: elemento.ruta },
          { etiqueta: hijo.etiqueta },
        ]
      }
      if (elemento.ruta === rutaActual) {
        return [{ etiqueta: elemento.etiqueta }]
      }
    }
    return []
  }

  /** Todas las entradas en plano — útil para un buscador de comandos. */
  function entradasPlanas(): { etiqueta: string; ruta: string; contexto?: string }[] {
    return MENU_ADMIN.flatMap((elemento) => [
      { etiqueta: elemento.etiqueta, ruta: elemento.ruta },
      ...(elemento.hijos ?? []).map((hijo) => ({
        etiqueta: hijo.etiqueta,
        ruta: hijo.ruta,
        contexto: elemento.etiqueta,
      })),
    ])
  }

  return { secciones, menu: MENU_ADMIN, migasPara, entradasPlanas }
}
