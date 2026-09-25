export type SupportedLocale = {
    code: string;
    name: string;
};

export type I18n = {
    locale: string;
    fallbackLocale: string;
    translations: Record<string, string>;
    supported: SupportedLocale[];
};
