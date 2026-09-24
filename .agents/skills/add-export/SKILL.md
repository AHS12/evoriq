---
name: add-export
description: 'Register a new exportable entity on Evoriq''s async export pipeline (exporter class + DataEntity enum + tests).'
argument-hint: 'Entity name, for example: Entry or Report.'
---

# Add Export

## When to use

When a new entity must be exportable to CSV/XLSX. Exports run asynchronously
through `App\Jobs\ProcessExport` on the `heavy` queue and are tracked with
`App\Models\DataProcessingJob`. See `AGENTS.md` §7.14 and TDR §33.

**Exports read from PostgreSQL — never query the Clockify API from an exporter.**

## Steps

1. **Write the exporter** in `app/Exports/{Entity}Export.php` implementing
   `App\Exports\Contracts\Exportable` (and `WithMapping` when rows need shaping).
   It receives a `filters` array from the queued job.

   ```php
   use App\Exports\Contracts\Exportable;
   use Illuminate\Support\Collection;
   use Maatwebsite\Excel\Concerns\WithMapping;

   /** @implements WithMapping<Entry> */
   class EntryExport implements Exportable, WithMapping
   {
       /** @param array<string, mixed> $filters */
       public function __construct(private array $filters = []) {}

       /** @return Collection<int, Entry> */
       public function collection(): Collection
       {
           // Query PostgreSQL only.
       }

       /** @param Entry $row */
       public function map($row): array
       {
           return [];
       }

       /** @return array<int, string> */
       public function headings(): array
       {
           return [];
       }
   }
   ```

2. **Register the entity** on `App\Enums\DataEntity`: add a case and a
   `makeExporter()` arm returning the exporter instance.

3. **Expose it to the UI** — `ExportController` (`StoreExportRequest` validates
   with `Rule::enum(DataEntity::class)`) accepts `entity_type` plus
   `format` (`csv`/`xlsx`) as soon as the enum case exists.

4. **Test it** — run the job with `Storage::fake('local')` and assert the file
   and database state. `tests/Feature/Export/ProcessExportJobTest.php` is the
   template.

## Notes

- Files are written to the disk in `config/exports.php` and downloaded through
  `ExportController@download`.
- Completed jobs and their files are pruned by the scheduled
  `data-processing:cleanup-completed` command.
- Use `tests/Mock/ExportMockData` for request payloads in tests.

## Completion checks

- [ ] Exporter implements `Exportable` and reads only PostgreSQL.
- [ ] `DataEntity` case + `makeExporter()` arm added.
- [ ] Job test asserts the generated file and DB state.
- [ ] `composer check` passes.
