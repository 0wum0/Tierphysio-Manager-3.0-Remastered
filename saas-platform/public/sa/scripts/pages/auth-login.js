const switchToTokenButton = document.querySelector('#switchToToken');
const switchToRegularButton = document.querySelector('#switchToRegular');
const regularLogin = document.querySelector('#regular-login');
const tokenLogin = document.querySelector('#token-login');

switchToTokenButton.addEventListener('click', function () {
    regularLogin.classList.add('d-none');
    tokenLogin.classList.remove('d-none');
});

switchToRegularButton.addEventListener('click', function () {
    tokenLogin.classList.add('d-none');
    regularLogin.classList.remove('d-none');
});