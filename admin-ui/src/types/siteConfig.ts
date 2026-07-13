export type SiteConfigField = {
  key: string
  type: 'bool' | 'int' | 'string' | 'html' | 'enum'
  label: string
  description?: string | null
  min?: number | null
  max?: number | null
  options?: Record<string, string> | null
}

export type SiteConfigGroup = {
  title: string
  fields: SiteConfigField[]
}

export type SiteConfigSchema = Record<string, SiteConfigGroup>
