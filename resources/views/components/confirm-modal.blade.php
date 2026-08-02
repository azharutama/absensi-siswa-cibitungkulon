<div 
    id="global-confirm-modal" 
    class="fixed inset-0 z-50 overflow-y-auto hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-modal-title"
    aria-describedby="confirm-modal-description"
    aria-hidden="true"
    tabindex="-1"
>
    <div data-confirm-modal-backdrop class="fixed inset-0 bg-gray-500/75 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>

    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-gray-100">
            
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:items-start sm:flex">
                    <div class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-full sm:mx-0 sm:h-10 sm:w-10" id="confirm-modal-icon-container">
                        <svg class="h-6 w-6" id="confirm-modal-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ms-4 sm:text-left">
                        <h3 id="confirm-modal-title" class="text-base font-semibold text-gray-900">Konfirmasi</h3>
                        <div class="mt-2">
                            <p id="confirm-modal-description" class="text-sm text-gray-500">Apakah Anda yakin ingin melanjutkan?</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                <form id="confirm-modal-form" method="POST" class="inline-block w-full sm:w-auto">
                    @csrf
                    <button type="submit" id="confirm-modal-submit" class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto">
                        Konfirmasi
                    </button>
                </form>
                <button 
                    id="confirm-modal-cancel"
                    data-close-confirm-modal
                    type="button" 
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:mt-0 sm:w-auto"
                >
                    Batal
                </button>
            </div>

        </div>
    </div>
</div>