export type FileSource = 'upload' | 'generated';

export type FileEntry = {
    id: number;
    source: FileSource;
    is_generated: boolean;
    name: string;
    file_name: string | null;
    mime_type: string | null;
    size: number | null;
    type: string;
    type_label: string;
    url: string | null;
    thumb_url: string | null;
    download_url: string | null;
    job_id: string | null;
    job_type: string | null;
    job_type_label: string | null;
    created_at: string | null;
};
