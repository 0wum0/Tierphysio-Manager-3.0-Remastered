



// Show comment actions when input is focused

document.addEventListener('DOMContentLoaded', function () {
    const commentInput = document.querySelector('.comment-input-wrapper input');
    const commentActions = document.querySelector('.comment-actions');
    if (commentInput && commentActions) {
        commentInput.addEventListener('focus', function () {
            commentActions.classList.remove('d-none');
        });
        document.addEventListener('click', function (e) {
            if (!commentInput.contains(e.target) && !commentActions.contains(e.target)) {
                if (commentInput.value.length === 0) {
                    commentActions.classList.add('d-none');
                }
            }
        });
    }
});


// Form validation for all modals
document.addEventListener('DOMContentLoaded', function () {
    // Get all forms that need validation
    const forms = document.querySelectorAll('.needs-validation');
    // Function to handle form submission
    function handleFormSubmit(event) {
        const form = event.target.closest('.modal').querySelector('form');
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        else {
            // If form is valid, you can add AJAX submission here
            // For demo purposes, we'll just close the modal
            const modal = bootstrap.Modal.getInstance(event.target.closest('.modal'));
            modal.hide();

            // Show success message (optional)
            // This would be replaced with actual form submission in a real application
            // alert('Form submitted successfully!');
        }
        form.classList.add('was-validated');
    }
    
    // Attach click event handlers to submit buttons
    document.getElementById('submitNewsForm').addEventListener('click', handleFormSubmit);
    document.getElementById('submitProjectForm').addEventListener('click', handleFormSubmit);
    document.getElementById('submitProfileForm').addEventListener('click', handleFormSubmit);
    
    // Optional: Add validation as user types for better UX
    Array.from(forms).forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('input', function () {
                if (!this.checkValidity()) {
                    this.classList.add('is-invalid');
                }
                else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });
    });
});