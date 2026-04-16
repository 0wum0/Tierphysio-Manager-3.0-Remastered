document.addEventListener('DOMContentLoaded', function() {
    // Image Preview Modal Functionality
    const imageTab = document.querySelector('#tab-images');
    if (imageTab) {
        // Create modal container with proper Bootstrap modal structure
        const modalContainer = document.createElement('div');
        modalContainer.className = 'modal fade image-preview-modal';
        modalContainer.id = 'imagePreviewModal';
        modalContainer.tabIndex = '-1';
        modalContainer.setAttribute('aria-hidden', 'true');

        // Create modal content
        modalContainer.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body p-0 position-relative d-flex align-items-center justify-content-center">
                        <div class="d-flex flex-column flex-lg-row shadow rounded overflow-hidden border border-3 border-light">
                            <!-- Left Info Panel -->
                            <div class="order-2 order-lg-1 flex-shrink-0" style="width: 300px;">
                                <div class="d-flex flex-column h-100 p-0">
                                    <div class="flex-grow-1 p-3">
                                        <h5 class="image-title fw-bold mb-3"></h5>
                                        <p class="image-description text-muted mb-4"></p>
                                        
                                        <div class="mb-3">
                                            <div class="text-muted mb-1 fs-sm">Date</div>
                                            <div class="image-date"></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="text-muted mb-1 fs-sm">Source</div>
                                            <div class="image-source text-primary text-decoration-underline link-offset-1 link-underline link-underline-opacity-75"></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="text-muted mb-1 fs-sm">Tags</div>
                                            <div class="image-category badge bg-secondary"></div>
                                        </div>
                                    </div>
                                    <div class="px-3 pt-2 pb-0">
                                        <p class="text-muted fs-nano mb-2">
                                            Images may be subject to copyright. Please check the source for more information.
                                        </p>
                                        <div class="d-flex gap-2 pb-3">
                                            <button type="button" class="btn btn-default btn-sm flex-grow-1">
                                                Save
                                            </button>
                                            <button type="button" class="btn btn-default btn-sm flex-grow-1">
                                                Share
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Image Container -->
                            <div class="position-relative bg-light order-1 order-lg-2" style="width: auto; height: auto; max-height: 90vh;">
                                <button type="button" class="btn btn-icon btn-danger border border-dark position-absolute top-0 end-0 m-2 z-1" data-bs-dismiss="modal" aria-label="Close">
                                    <svg class="sa-icon sa-bold sa-icon-2x sa-icon-light">
                                        <use href="icons/sprite.svg#x"></use>
                                    </svg>
                                </button>
                                <div class="d-flex align-items-center justify-content-center h-100 p-0">
                                    <button type="button" class="btn btn-icon align-items-center justify-content-center text-light btn-dark bg-dark bg-opacity-50 rounded-circle position-absolute top-50 start-0 translate-middle-y ms-4 d-none d-sm-flex fs-3 z-1" id="prevImage">
                                        <i class="sa sa-chevron-left"></i>
                                    </button>
                                    
                                    <img src="" class="img-preview" style="max-height: 90vh; max-width: 100%; object-fit: contain;" alt="Preview">
                                    
                                    <button type="button" class="btn btn-icon align-items-center justify-content-center text-light btn-dark bg-dark bg-opacity-50 rounded-circle position-absolute top-50 end-0 translate-middle-y me-4 d-none d-sm-flex fs-3 z-1" id="nextImage">
                                        <i class="sa sa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalContainer);

        // Initialize Bootstrap modal
        const modal = new bootstrap.Modal(modalContainer);

        // Get all preview images
        const previewImages = imageTab.querySelectorAll('a[href="#"]');
        let currentImageIndex = 0;

        // Add click event to each preview image
        previewImages.forEach((link, index) => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const imgElement = link.querySelector('img');
                const imgSrc = imgElement.src;
                // Add -big before the file extension
                const bigImgSrc = imgSrc.replace(/(\.[^.]+)$/, '-big$1');
                showPreview(bigImgSrc, link);
                currentImageIndex = index;
                updateNavigationButtons();
            });
        });

        // Navigation buttons
        const prevBtn = modalContainer.querySelector('#prevImage');
        const nextBtn = modalContainer.querySelector('#nextImage');
        
        prevBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (currentImageIndex > 0) {
                currentImageIndex--;
                const link = previewImages[currentImageIndex];
                const imgElement = link.querySelector('img');
                const imgSrc = imgElement.src;
                const bigImgSrc = imgSrc.replace(/(\.[^.]+)$/, '-big$1');
                updatePreview(bigImgSrc, link);
                updateNavigationButtons();
            }
        });

        nextBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (currentImageIndex < previewImages.length - 1) {
                currentImageIndex++;
                const link = previewImages[currentImageIndex];
                const imgElement = link.querySelector('img');
                const imgSrc = imgElement.src;
                const bigImgSrc = imgSrc.replace(/(\.[^.]+)$/, '-big$1');
                updatePreview(bigImgSrc, link);
                updateNavigationButtons();
            }
        });

        // Helper functions
        function showPreview(imgSrc, link) {
            updatePreview(imgSrc, link);
            modal.show();
        }

        function updatePreview(imgSrc, link) {
            const previewImg = modalContainer.querySelector('.img-preview');
            previewImg.src = imgSrc;
            
            // Update image information
            modalContainer.querySelector('.image-title').textContent = link.dataset.imgTitle || '';
            modalContainer.querySelector('.image-description').textContent = link.dataset.imgDescription || '';
            modalContainer.querySelector('.image-category').textContent = link.dataset.imgCategory || '';
            modalContainer.querySelector('.image-date').textContent = link.dataset.imgDate || '';
            modalContainer.querySelector('.image-source').textContent = link.dataset.imgSource || '';
        }

        function hidePreview() {
            modal.hide();
        }

        function updateNavigationButtons() {
            prevBtn.classList.toggle('hidden', currentImageIndex === 0);
            nextBtn.classList.toggle('hidden', currentImageIndex === previewImages.length - 1);
        }

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (!modalContainer.classList.contains('show')) return;
            
            switch(e.key) {
                case 'Escape':
                    hidePreview();
                    break;
                case 'ArrowLeft':
                    if (currentImageIndex > 0) prevBtn.click();
                    break;
                case 'ArrowRight':
                    if (currentImageIndex < previewImages.length - 1) nextBtn.click();
                    break;
            }
        });

        // Clean up modal backdrop when modal is hidden
        modalContainer.addEventListener('hidden.bs.modal', function () {
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        });
    }
});