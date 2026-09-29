<x-filament-panels::page>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b">
                    <th class="py-2">ID</th>
                    <th>Type</th>
                    <th>Mode</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Updated</th>
                    <th>Created</th>
                    <th>Skipped</th>
                    <th>Failed</th>
                    <th>Started</th>
                    <th>Report</th>
                </tr>
            </thead>
            <tbody>
                @foreach($this->runs as $run)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="py-2">#{{ $run->id }}</td>
                        <td>{{ $run->type }}</td>
                        <td>{{ $run->mode }}@if($run->dry_run) / dry@endif</td>
                        <td>{{ $run->status }}</td>
                        <td>{{ $run->processed_rows }}/{{ $run->total_rows }} ({{ $run->progressPercent() }}%)</td>
                        <td>{{ $run->updated_rows }}</td>
                        <td>{{ $run->created_rows }}</td>
                        <td>{{ $run->skipped_rows }}</td>
                        <td>{{ $run->failed_rows }}</td>
                        <td>{{ $run->started_at }}</td>
                        <td>
                            @if($run->report_path)
                                <button type="button" wire:click="downloadReport({{ $run->id }})" class="text-primary-600 font-semibold">Download skipped</button>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->runs->links() }}</div>
</x-filament-panels::page>
