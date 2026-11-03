@props([
    'name' => 'date',
    'id' => 'date',
    'label' => null,
    'model' => 'date',
    'required' => false,
])

<div
    class="relative"
    x-data="{
        open: false,
        dropUp: false,
        viewYear: new Date().getFullYear(),
        viewMonth: new Date().getMonth(),
        months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        
        get currentValue() {
            return {{ $model }};
        },

        get formattedDisplay() {
            const val = this.currentValue;
            if (!val) return 'Select date';
            const parts = String(val).split('-');
            if (parts.length !== 3) return val;
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        },

        get monthName() {
            return this.months[this.viewMonth];
        },

        get calendarDays() {
            const days = [];
            const firstDay = new Date(this.viewYear, this.viewMonth, 1).getDay();
            const totalDays = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();

            for (let i = 0; i < firstDay; i++) {
                days.push({ day: null, dateStr: '' });
            }

            for (let d = 1; d <= totalDays; d++) {
                const mStr = String(this.viewMonth + 1).padStart(2, '0');
                const dStr = String(d).padStart(2, '0');
                days.push({
                    day: d,
                    dateStr: `${this.viewYear}-${mStr}-${dStr}`
                });
            }

            return days;
        },

        isToday(dateStr) {
            const now = new Date();
            const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
            return dateStr === todayStr;
        },

        isSelected(dateStr) {
            return this.currentValue === dateStr;
        },

        selectDate(dateStr) {
            {{ $model }} = dateStr;
            this.open = false;
        },

        selectToday() {
            const now = new Date();
            const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
            this.viewYear = now.getFullYear();
            this.viewMonth = now.getMonth();
            this.selectDate(todayStr);
        },

        clearDate() {
            {{ $model }} = '';
            this.open = false;
        },

        prevMonth() {
            if (this.viewMonth === 0) {
                this.viewMonth = 11;
                this.viewYear--;
            } else {
                this.viewMonth--;
            }
        },

        nextMonth() {
            if (this.viewMonth === 11) {
                this.viewMonth = 0;
                this.viewYear++;
            } else {
                this.viewMonth++;
            }
        },

        toggle(el) {
            this.open = !this.open;
            if (this.open) {
                if (this.currentValue) {
                    const parts = String(this.currentValue).split('-');
                    if (parts.length === 3) {
                        this.viewYear = parseInt(parts[0], 10);
                        this.viewMonth = parseInt(parts[1], 10) - 1;
                    }
                }
                const rect = el.getBoundingClientRect();
                this.dropUp = (window.innerHeight - rect.bottom) < 320;
            }
        }
    }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    @if ($label)
        <label for="{{ $id }}" class="mb-2 block text-sm font-medium text-ink">
            {{ $label }}
        </label>
    @endif

    {{-- Trigger Input/Button --}}
    <button
        id="{{ $id }}"
        type="button"
        @click="toggle($el)"
        class="flex w-full items-center justify-between rounded-lg border border-line bg-white px-3.5 py-3 text-sm font-medium text-ink shadow-sm transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 cursor-pointer"
        :class="open ? 'border-brand ring-2 ring-brand/20' : ''"
    >
        <span x-text="formattedDisplay" class="font-medium text-ink"></span>
        <x-icon name="calendar" size="h-4 w-4" class="text-muted shrink-0" />
    </button>

    {{-- Hidden Native Date Input --}}
    <input
        type="date"
        name="{{ $name }}"
        x-model="{{ $model }}"
        class="sr-only"
        tabindex="-1"
        aria-hidden="true"
        @if ($required) required @endif
    >

    {{-- Calendar Popover --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute left-0 z-50 w-72 rounded-2xl border border-line bg-white p-3.5 shadow-2xl ring-1 ring-black/5 sm:w-80"
        :class="dropUp ? 'bottom-full mb-2 origin-bottom-left' : 'top-full mt-2 origin-top-left'"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-line/60">
            <span class="text-sm font-bold text-ink" x-text="`${monthName} ${viewYear}`"></span>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    @click.stop="prevMonth()"
                    class="flex h-7 w-7 items-center justify-center rounded-lg text-muted hover:bg-page hover:text-ink transition cursor-pointer"
                    title="Previous month"
                >
                    <x-icon name="chevron-left" size="h-4 w-4" />
                </button>
                <button
                    type="button"
                    @click.stop="nextMonth()"
                    class="flex h-7 w-7 items-center justify-center rounded-lg text-muted hover:bg-page hover:text-ink transition cursor-pointer"
                    title="Next month"
                >
                    <x-icon name="chevron-right" size="h-4 w-4" />
                </button>
            </div>
        </div>

        {{-- Weekday Names --}}
        <div class="mt-2.5 grid grid-cols-7 text-center text-[11px] font-semibold text-muted uppercase tracking-wider">
            <span>Su</span>
            <span>Mo</span>
            <span>Tu</span>
            <span>We</span>
            <span>Th</span>
            <span>Fr</span>
            <span>Sa</span>
        </div>

        {{-- Day Cells --}}
        <div class="mt-2 grid grid-cols-7 gap-1 text-center text-sm">
            <template x-for="(cell, idx) in calendarDays" :key="idx">
                <div>
                    <template x-if="cell.day">
                        <button
                            type="button"
                            @click.stop="selectDate(cell.dateStr)"
                            class="flex h-8 w-8 mx-auto items-center justify-center rounded-lg text-xs font-medium transition cursor-pointer"
                            :class="{
                                'bg-brand text-white font-bold shadow-sm': isSelected(cell.dateStr),
                                'border border-brand text-brand font-semibold': isToday(cell.dateStr) && !isSelected(cell.dateStr),
                                'text-ink hover:bg-brand-soft hover:text-brand': !isSelected(cell.dateStr) && !isToday(cell.dateStr)
                            }"
                            x-text="cell.day"
                        ></button>
                    </template>
                    <template x-if="!cell.day">
                        <div class="h-8 w-8 mx-auto"></div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Footer actions --}}
        <div class="mt-3 pt-2.5 border-t border-line/60 flex items-center justify-between text-xs">
            <button
                type="button"
                @click.stop="selectToday()"
                class="font-semibold text-brand hover:underline cursor-pointer"
            >
                Today
            </button>

            <button
                type="button"
                @click.stop="clearDate()"
                class="font-medium text-muted hover:text-expense cursor-pointer"
            >
                Clear
            </button>
        </div>
    </div>
</div>
