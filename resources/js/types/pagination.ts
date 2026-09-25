export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: PaginationMeta;
};

export type CursorPaginationMeta = {
    per_page: number;
    next_cursor: string | null;
    prev_cursor: string | null;
};

export type CursorPaginated<T> = {
    data: T[];
    meta: CursorPaginationMeta;
};
