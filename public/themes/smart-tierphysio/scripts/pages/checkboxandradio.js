document.addEventListener("DOMContentLoaded", function () {
    // Set the indeterminate state for the checkbox
    document.getElementById("defaultIndeterminate").indeterminate = true;
    // Get buttons
    const checkboxToggleBtn = document.getElementById("js-checkbox-toggle");
    const radioToggleBtn = document.getElementById("js-radio-toggle");
    // Add click event listeners
    if (checkboxToggleBtn) {
        checkboxToggleBtn.addEventListener("click", toggleCheckbox);
    }
    if (radioToggleBtn) {
        radioToggleBtn.addEventListener("click", toggleRadio);
    }

    // Function to toggle checkbox styles
    function toggleCheckbox() {
        let checkboxes = document.querySelectorAll(".demo-checkbox .form-check-input");
        toggleText(this, "Change to CIRCLE", "Change back to default");
        checkboxes.forEach(checkbox => checkbox.classList.toggle("rounded-circle"));
    }

    // Function to toggle radio button styles
    function toggleRadio() {
        let radios = document.querySelectorAll(".demo-radio .form-check-input");
        toggleText(this, "Change to ROUNDED", "Change back to default");
        radios.forEach(radio => radio.classList.toggle("rounded"));
    }

    // Function to toggle button text
    function toggleText(element, text1, text2) {
        element.textContent = element.textContent.trim() === text1 ? text2 : text1;
    }
});