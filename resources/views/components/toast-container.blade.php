<div
    x-data="{
        toasts: [],
        add(detail) {
            const toast = { id: Date.now() + Math.random(), message: detail.message ?? '', variant: detail.variant ?? 'info' };
            this.toasts.push(toast);
            setTimeout(() => { this.toasts = this.toasts.filter((t) => t.id !== toast.id); }, 4000);
        },
    }"
    x-on:toast.window="add($event.detail)"
    @if (session()->has('toast')) x-init="add(@js(session('toast')))" @endif
    class="pointer-events-none fixed bottom-4 end-4 z-[60] flex w-80 flex-col gap-2"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            class="pointer-events-auto rounded-lg px-4 py-3 text-sm text-white shadow-lg"
            x-bind:class="toast.variant === 'success' ? 'bg-success' : 'bg-zinc-800'"
            x-text="toast.message"
        ></div>
    </template>
</div>
