<?php

use App\Support\ClinicContext;
use App\Support\ClinicReports;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(): void
    {
        $allowed = $this->allowedTypes();

        abort_if($allowed === [], 403);

        if (! array_key_exists($this->type, $allowed)) {
            $this->type = array_key_first($allowed);
        }

        $this->from = $this->clean($this->from, today()->startOfMonth());
        $this->to = $this->clean($this->to, today());
    }

    protected function clean(string $value, Carbon $fallback): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            return $fallback->format('Y-m-d');
        }
    }

    /** The reports this person may open. */
    public function allowedTypes(): array
    {
        $user = auth('admin')->user();

        return collect(ClinicReports::TYPES)->filter(fn ($t) => $user->can($t[1]))->map(fn ($t) => $t[0])->all();
    }

    public function setType(string $type): void
    {
        if (array_key_exists($type, $this->allowedTypes())) {
            $this->type = $type;
        }
    }

    public function setRange(string $range): void
    {
        [$from, $to] = match ($range) {
            'today' => [today(), today()],
            'week' => [today()->startOfWeek(), today()],
            'month' => [today()->startOfMonth(), today()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'year' => [today()->startOfYear(), today()],
            default => [today()->startOfMonth(), today()],
        };

        $this->from = $from->format('Y-m-d');
        $this->to = $to->format('Y-m-d');
    }

    /** Why the chosen dates cannot be used, or null when they are fine. */
    #[Computed]
    public function rangeError(): ?string
    {
        try {
            $from = Carbon::createFromFormat('Y-m-d', $this->from)->startOfDay();
            $to = Carbon::createFromFormat('Y-m-d', $this->to)->startOfDay();
        } catch (\Throwable) {
            return 'Please choose a valid start and end date.';
        }

        if ($to->lt($from)) {
            return 'The end date must be on or after the start date.';
        }

        return $from->diffInDays($to) > 366 ? 'Please choose a period of one year or less.' : null;
    }

    #[Computed]
    public function report(): ?array
    {
        if (! array_key_exists($this->type, $this->allowedTypes()) || $this->rangeError !== null) {
            return null;
        }

        return ClinicReports::build($this->type, Carbon::parse($this->from), Carbon::parse($this->to), ClinicContext::id());
    }
    public function exportCsv()
    {
        abort_unless(array_key_exists($this->type, $this->allowedTypes()), 403);

        $report = $this->report;

        abort_if($report === null, 422);

        $name = 'report-'.$this->type.'-'.$this->from.'-to-'.$this->to.'.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads the file as UTF-8
            ClinicReports::toCsv($report, $out);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
?>

<div>
    <x-slot:header>
        <h2 class="text-lg font-semibold text-gray-900">Reports</h2>
    </x-slot:header>

    @php $types = $this->allowedTypes(); $report = $this->report; @endphp

    <div class="mb-4 flex flex-wrap gap-1 border-b border-gray-200">
        @foreach ($types as $key => $label)
            <button type="button" wire:click="setType('{{ $key }}')" wire:key="type-{{ $key }}"
                class="-mb-px border-b-2 px-4 py-2 text-sm font-medium {{ $type === $key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($type !== 'pending')
        <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
            <div>
                <label class="block text-xs font-medium text-gray-600">From</label>
                <input wire:model.live="from" type="date" class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">To</label>
                <input wire:model.live="to" type="date" class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year'] as $key => $label)
                    <button type="button" wire:click="setRange('{{ $key }}')" class="rounded-lg border border-gray-300 px-2.5 py-1.5 font-medium text-gray-700 hover:bg-gray-50">{{ $label }}</button>
                @endforeach
            </div>
            @if ($this->rangeError) <p class="w-full text-sm text-red-600">{{ $this->rangeError }}</p> @endif
        </div>
    @endif

    @if ($report)
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-gray-900">{{ $report['title'] }}@if ($type !== 'pending') <span class="ml-1 text-sm font-normal text-gray-500">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</span>@endif</h3>
            <button type="button" wire:click="exportCsv" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Download CSV</button>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
            @foreach ($report['summary'] as $label => $value)
                <div wire:key="sum-{{ $loop->index }}" class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>@foreach ($report['headings'] as $h) <th class="whitespace-nowrap px-4 py-3 font-medium">{{ $h }}</th> @endforeach</tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($report['rows'] as $row)
                        <tr wire:key="row-{{ $loop->index }}">@foreach ($row as $cell) <td class="px-4 py-2.5 text-gray-700">{{ $cell }}</td> @endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($report['headings']) }}" class="px-4 py-10 text-center text-gray-400">Nothing to show for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
