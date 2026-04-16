function swapPositions() {
    // Toggle the 'position-relative' class on the element with id 'js-position-change'
    var positionChangeEl = document.getElementById("js-position-change");
    if (positionChangeEl) {
        positionChangeEl.classList.toggle("position-relative");
    }
    // Helper function to toggle text content between two given strings
    function toggleText(element, text1, text2) {
        if (!element) return;
        // Check current text (trimmed of whitespace)
        if (element.textContent.trim() === text1) {
            element.textContent = text2;
        }
        else {
            element.textContent = text1;
        }
    }
    // Toggle the text for the element with id 'js-position-text'
    var positionTextEl = document.getElementById("js-position-text");
    toggleText(positionTextEl, ".position-static", ".position-relative");
    // Toggle the text for the element with id 'js-position-btn'
    var positionBtnEl = document.getElementById("js-position-btn");
    toggleText(positionBtnEl, "Change to RELATIVE", "Change to STATIC");
}