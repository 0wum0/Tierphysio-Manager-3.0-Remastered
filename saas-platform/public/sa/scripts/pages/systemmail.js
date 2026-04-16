// Cache DOM elements
let emailList;
let selectAllCheckbox;
let refreshButton;
let deleteButton;
let spamButton;
let composeModal;
let sendButton;
let saveDraftButton;
let showMoreButton;
let attachmentsContainer;

// Initialize the application
document.addEventListener('DOMContentLoaded', function () {
    initializeElements();
    loadEmails();
    setupEventListeners();
    setupAttachments();
});

// Initialize DOM elements
function initializeElements() {
    emailList = document.getElementById('js-emails');
    selectAllCheckbox = document.getElementById('js-msg-select-all');
    refreshButton = document.querySelector('.js-refresh').closest('button');
    deleteButton = document.querySelector('.js-delete').closest('button');
    spamButton = document.querySelector('.js-spam').closest('button');
    composeModal = document.getElementById('default-example-modal-lg-center');
    sendButton = composeModal?.querySelector('.btn-primary');
    saveDraftButton = composeModal?.querySelector('.btn-secondary');
    showMoreButton = document.querySelector('a.fs-xs.text-secondary');
    attachmentsContainer = document.getElementById('message-attachments');
}

// Setup event listeners
function setupEventListeners() {
    // Star toggling
    emailList.addEventListener('click', handleEmailInteractions);

    // Checkbox selection
    selectAllCheckbox?.addEventListener('change', handleSelectAll);

    // Refresh button
    refreshButton?.addEventListener('click', handleRefresh);

    // Delete functionality
    deleteButton?.addEventListener('click', handleDelete);

    // Spam functionality
    spamButton?.addEventListener('click', handleSpam);

    // Send email
    sendButton?.addEventListener('click', handleSendEmail);

    // Save draft
    saveDraftButton?.addEventListener('click', handleSaveDraft);

    // Show more attachments
    showMoreButton?.addEventListener('click', handleShowMore);
}

// Handle all email interactions (star, click to read)
function handleEmailInteractions(e) {
    // Handle star clicking
    const starIcon = e.target.closest('.mail-starred');
    if (starIcon) {
        const listItem = starIcon.closest('li');
        const emailId = listItem.querySelector('.form-check-input').id.replace('msg-', '');

        // Toggle starred class
        listItem.classList.toggle('starred');

        // Update in localStorage
        const emails = JSON.parse(localStorage.getItem('emails') || '[]');
        const email = emails.find(email => email.id === emailId);
        if (email) {
            email.starred = !email.starred;
            localStorage.setItem('emails', JSON.stringify(emails));
        }

        // Prevent further handling
        e.stopPropagation();
        return;
    }

    // Handle clicking on email content to read
    const mailSender = e.target.closest('.js-email-content');

    if (mailSender) {
        // Navigate to read page
        window.location.href = 'systemmail-read.html';
    }
}

// Handle select all checkbox
function handleSelectAll(e) {
    const checkboxes = emailList.querySelectorAll('.form-check-input');
    checkboxes.forEach(checkbox => {
        checkbox.checked = e.target.checked;
    });
}

// Handle refresh button
function handleRefresh() {
    emailList.classList.add('refreshing');

    // Show loading spinner
    refreshButton.querySelector('i').classList.add('fa-spin');

    // Simulate refresh
    setTimeout(() => {
        loadEmails(); // Reload emails
        emailList.classList.remove('refreshing');
        refreshButton.querySelector('i').classList.remove('fa-spin');
    }, 1000);
}

// Handle delete functionality
function handleDelete() {
    const selectedEmails = emailList.querySelectorAll('.form-check-input:checked');
    if (selectedEmails.length === 0) return;

    // Count for toast message
    const count = selectedEmails.length;

    // Process each selected email
    selectedEmails.forEach(checkbox => {
        const listItem = checkbox.closest('li');

        // Add deleting class for animation
        listItem.style.overflow = 'hidden';
        listItem.classList.add('deleting');

        // Remove after animation completes
        setTimeout(() => {
            listItem.remove();
        }, 300);
    });

    // Show toast message
    showToast(`${count} message${count > 1 ? 's' : ''} deleted`);
}

// Handle spam functionality
function handleSpam() {
    const selectedEmails = emailList.querySelectorAll('.form-check-input:checked');
    if (selectedEmails.length === 0) return;

    // Count for toast message
    const count = selectedEmails.length;

    // Process each selected email
    selectedEmails.forEach(checkbox => {
        const listItem = checkbox.closest('li');

        // Add deleting class for animation
        listItem.style.overflow = 'hidden';
        listItem.classList.add('deleting');

        // Remove after animation completes
        setTimeout(() => {
            listItem.remove();
        }, 300);
    });

    // Show toast message
    showToast(`${count} message${count > 1 ? 's' : ''} moved to spam`);
}

// Handle send email
function handleSendEmail(e) {
    e.preventDefault();

    // Get form data
    const recipients = document.getElementById('message-to').value;
    const subject = document.querySelector('input[placeholder="Subject"]').value;
    const content = document.getElementById('fake_textarea').innerHTML;

    // Validate
    if (!recipients || !subject || !content) {
        alert('Please fill in all required fields');
        return;
    }

    // Close modal
    const modal = bootstrap.Modal.getInstance(composeModal);
    modal.hide();

    // Show success toast
    showToast('Email sent successfully!');

    // Reset form completely
    resetComposeForm(true);
}

// Handle save draft
function handleSaveDraft(e) {
    e.preventDefault();

    // Close modal
    const modal = bootstrap.Modal.getInstance(composeModal);
    modal.hide();

    // Show success toast with check mark
    showToast('<i class="fas fa-check me-2"></i> Draft saved', 'success');
}

// Show toast message
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `mail-toast bg-${type}-500`;
    toast.innerHTML = message;
    document.body.appendChild(toast);

    // Show toast
    setTimeout(() => toast.classList.add('show'), 100);

    // Remove toast
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Reset compose form
function resetComposeForm(clearAll = false) {
    document.getElementById('message-to').value = '';
    document.getElementById('message-to-cc').value = '';
    document.querySelector('input[placeholder="Subject"]').value = '';

    // Reset attachments
    if (attachmentsContainer) {
        if (clearAll) {
            // Clear all attachments
            attachmentsContainer.innerHTML = '';

            // Add back the "show more" link
            const showMoreLink = document.createElement('a');
            showMoreLink.href = '#';
            showMoreLink.className = 'fs-xs text-secondary';
            showMoreLink.textContent = 'show 3 more';
            showMoreLink.addEventListener('click', handleShowMore);
            attachmentsContainer.appendChild(showMoreLink);

            // Hide the container if it should be empty
            attachmentsContainer.classList.add('if-empty-display-none');
        } else {
            // Keep only the first two default attachments
            const attachments = attachmentsContainer.querySelectorAll('.alert');
            attachments.forEach((attachment, index) => {
                if (index > 1) {
                    attachment.remove();
                } else {
                    attachment.classList.remove('hidden-attachment');
                }
            });

            // Reset show more link
            if (showMoreButton) {
                showMoreButton.style.display = 'none';
            }
        }
    }

    // Reset textarea with signature
    document.getElementById('fake_textarea').innerHTML = `
    <p><br></p>
    <p><br></p>
    <p>Best regards,</p>
    <div class="d-flex d-column align-items-start mb-3 gap-2">
        <img src="img/demo/avatars/avatar-admin.png" alt="SmartAdmin WebApp" class="me-3 mt-1 rounded-circle width-2">
        <div class="border-left pl-3">
            <span class="fw-500 fs-lg d-block l-h-n">Sunny A.</span>
            <span class="fw-400 fs-nano d-block l-h-n mb-1">Software Engineer</span>
        </div>
    </div>
    <div class="text-muted fs-nano">
        PRIVATE AND CONFIDENTIAL. This e-mail, its contents and attachments are private and confidential and is intended for the recipient only. Any disclosure, copying or unauthorized use of such information is prohibited. If you receive this message in error, please notify us immediately and delete the original and any copies and attachments.
    </div>
    `;
}

// Handle show more attachments
function handleShowMore(e) {
    e.preventDefault();

    const hiddenAttachments = document.querySelectorAll('.hidden-attachment');

    // If there are hidden attachments, show them
    if (hiddenAttachments.length > 0) {
        hiddenAttachments.forEach(attachment => {
            attachment.classList.remove('hidden-attachment');
        });
        e.target.textContent = 'hide attachments';
    }
    // Otherwise, hide all but the first two attachments
    else {
        const allAttachments = attachmentsContainer.querySelectorAll('.alert');
        allAttachments.forEach((attachment, index) => {
            if (index > 1) {
                attachment.classList.add('hidden-attachment');
            }
        });

        // Update show more text
        updateShowMoreText();
    }
}

// Setup attachments in the compose modal
function setupAttachments() {
    if (!attachmentsContainer) return;

    // Create additional hidden attachments
    const hiddenAttachments = [
        { name: 'report.docx', type: 'primary' },
        { name: 'presentation.pptx', type: 'primary' },
        { name: 'data.xlsx', type: 'primary' }
    ];

    // Add hidden attachments
    hiddenAttachments.forEach((attachment, index) => {
        const attachmentEl = document.createElement('div');
        attachmentEl.className = `alert m-0 p-0 badge bg-${attachment.type}-50 border-${attachment.type} ps-2 ${index > 0 ? 'hidden-attachment' : ''}`;
        attachmentEl.innerHTML = `${attachment.name} <button data-bs-dismiss="alert" class="btn btn-icon btn-xs ms-1 rounded-0 border border-${attachment.type} border-top-0 border-bottom-0 border-end-0" type="button">
            <i class="fas fa-times"></i>
        </button>`;

        // Insert before the "show more" link
        attachmentsContainer.insertBefore(attachmentEl, showMoreButton);
    });

    // Update show more link text
    updateShowMoreText();
}

// Update "show more" text based on hidden attachments
function updateShowMoreText() {
    if (!showMoreButton) return;

    const hiddenAttachments = document.querySelectorAll('.hidden-attachment');
    if (hiddenAttachments.length > 0) {
        showMoreButton.textContent = `show ${hiddenAttachments.length} more`;
        showMoreButton.style.display = '';
    } else {
        showMoreButton.style.display = 'none';
    }
}

// Load emails from JSON
function loadEmails() {
    fetch('./json/MOCK_MAIL.json')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (!emailList) {
                console.error('Email list container not found!');
                return;
            }

            // Store in localStorage for star functionality
            localStorage.setItem('emails', JSON.stringify(data));

            // Clear existing content
            emailList.innerHTML = '';

            // Iterate over each email
            data.forEach((email, index) => {
                const li = document.createElement('li');
                li.className = `${email.read ? '' : 'unread'} ${email.starred ? 'starred' : ''}`.trim();
                li.style.cursor = 'pointer';

                const checkboxId = `msg-${email.id}`;
                const time = new Date(email.timestamp).toLocaleTimeString([], {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });

                li.innerHTML = `
                <div class="d-flex align-items-center px-3 px-sm-4 py-2 py-lg-0 height-4 height-mobile-auto gap-2">
                    <div class="form-check form-check-hitbox me-2 order-1 mb-0">
                        <input type="checkbox" class="form-check-input" id="${checkboxId}">
                        <label class="form-check-label" for="${checkboxId}"></label>
                    </div>
                    <div class="d-flex align-self-end align-self-lg-center order-3 order-lg-2 me-lg-3 me-0 mb-1 mb-lg-0 flex-shrink-0">
                        <svg class="mail-starred sa-icon">
                            <use href="icons/sprite.svg#star"></use>
                        </svg>
                    </div>
                    <div class="js-email-content d-flex flex-column flex-lg-row flex-grow-1 align-items-stretch order-2 order-lg-3" style="min-width: 0;">
                        <div class="mail-sender flex-shrink-0 align-self-start align-self-lg-center width-sm width-max-sm text-truncate">${email.sender}</div>
                        <div class="d-flex flex-column flex-lg-row flex-grow-1 w-100 overflow-hidden">
                            <div class="mail-subject flex-shrink-0 align-self-start align-self-lg-center me-2 text-truncate width-max-100">${email.subject}</div>
                            <div class="d-flex align-items-center flex-grow-1 w-100 overflow-hidden">
                                <div class="mail-body d-block text-truncate w-100 pe-lg-5 text-muted">
                                    <span class="hidden-sm">-</span> ${email.bodyPreview}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="fs-sm text-muted ms-auto hide-on-hover-parent order-4 position-on-mobile-absolute pos-top pos-right pt-1 pt-lg-0 mt-2 me-3 me-sm-4 mt-lg-0 me-lg-0 flex-shrink-0">${time}</div>
                </div>
            `;

                // Prevent checkbox from triggering navigation
                const checkbox = li.querySelector('.form-check-input');
                checkbox.addEventListener('click', (e) => {
                    e.stopPropagation();
                });

                emailList.appendChild(li);
            });
        })
        .catch(error => console.error('Error loading emails:', error));
}
