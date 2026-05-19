<div x-show="toast.show" x-transition
     class="fixed bottom-4 right-4 z-[100] max-w-sm rounded-lg px-4 py-3 text-sm text-white shadow-lg"
     :class="toast.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
     x-text="toast.message"></div>
