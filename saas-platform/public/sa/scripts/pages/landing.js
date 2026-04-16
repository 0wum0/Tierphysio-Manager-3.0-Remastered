
VANTA.HALO({
    el: "#net",
    mouseControls: false,
    touchControls: false,
    gyroControls: false,
    color: 0xfd3995,
    size: 1.6,
    scale: 0.75,
    xOffset: 0.22,
    scaleMobile: 0.50,
});


// Typewriter effect script (ES5)
var textElement = document.getElementById('typewriter-text'); // Element to display text
var messages = [
    "The world's first Admin WebApp built with Artificial Intelligence",
    "AI‑ready by design: pre‑written prompt instructions included",
    "An advanced, jQuery‑free Bootstrap 5 Admin Dashboard UI",
    "Built for the next generation of enterprise web applications",
    "Clean, scalable, and engineered for future‑forward growth",
    "Continuously updated by a team of expert developers",
    "Enterprise-grade performance with beautiful design",
    "One dashboard template, unlimited use cases",
    "Modern, responsive UI built for serious development"
];

var currentMessageIndex = 0;
var isDeleting = false;
var typingSpeed = 15; // Speed of typing/deleting in milliseconds
var pauseSpeed = 3000; // Pause between cycles in milliseconds
function typeWrite() {
    var fullText = messages[currentMessageIndex];
    var currentText = textElement.textContent;
    var isEndOfMessage = !isDeleting && currentText === fullText;
    var isStartOfMessage = isDeleting && currentText === '';
    if (isEndOfMessage) {
        setTimeout(function () {
            isDeleting = true;
            typeWrite();
        }, pauseSpeed);
        return;
    }
    if (isStartOfMessage) {
        isDeleting = false;
        currentMessageIndex = (currentMessageIndex + 1) % messages.length;
        typeWrite();
        return;
    }
    if (isDeleting) {
        currentText = currentText.slice(0, -1);
    }
    else {
        currentText = fullText.slice(0, currentText.length + 1);
    }
    textElement.textContent = currentText;
    setTimeout(typeWrite, typingSpeed);
}

// Start the typewriter effect
if (textElement) {
    textElement.textContent = '';
    typeWrite();
}
else {
    console.error('Typewriter text element not found');
}