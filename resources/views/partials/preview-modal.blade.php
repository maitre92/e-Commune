{{-- Aperçu (bouton œil) d'une carte de vignette ou d'un extrait d'acte : le document s'affiche tel qu'il sera
     imprimé, dans une modale, puis « Imprimer ». Un lien `.nv-preview` porte `data-src` (page imprimable), `data-title`
     et, facultatif, `data-deliver` : l'identifiant de l'acte dont « Imprimer » délivre l'extrait (formulaire
     `form[data-deliver="…"]` de la page : extrait numéroté et encaissé) au lieu d'imprimer l'aperçu. --}}
<div class="modal fade" id="nvPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class='bx bx-show me-2'></i><span id="nvPreviewTitle">Aperçu</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body p-0" style="background:#e9e6df">
                <iframe id="nvPreviewFrame" title="Aperçu" style="width:100%;height:75vh;border:0;display:block"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="nvPreviewPrint"><i class='bx bx-printer me-1'></i>Imprimer</button>
            </div>
        </div>
    </div>
</div>
@push('scripts')
    <script>
        (() => {
            const modalEl = document.getElementById('nvPreviewModal');
            const frame = document.getElementById('nvPreviewFrame');
            const printBtn = document.getElementById('nvPreviewPrint');
            let deliver = null;
            document.addEventListener('click', (event) => {
                const link = event.target.closest('.nv-preview');
                if (!link) return;
                event.preventDefault();
                document.getElementById('nvPreviewTitle').textContent = link.dataset.title || 'Aperçu';
                deliver = link.dataset.deliver ? document.querySelector('form[data-deliver="' + link.dataset.deliver + '"]') : null;
                printBtn.classList.toggle('d-none', link.dataset.deliver !== undefined && !deliver);
                frame.src = link.dataset.src;
                (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
            });
            printBtn.addEventListener('click', () => {
                if (deliver) { deliver.submit(); return; }
                frame.contentWindow.focus();
                frame.contentWindow.print();
            });
            modalEl.addEventListener('hidden.bs.modal', () => { frame.src = 'about:blank'; });
        })();
    </script>
@endpush
