(function () {
    'use strict';

    var passwordInputs = document.querySelectorAll('#slfd_agent_password, .slfd-agent-password input[type="password"]');
    passwordInputs.forEach(function (input) {
        var wrapper = document.createElement('span');
        wrapper.className = 'slfd-password-field';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'slfd-password-toggle';
        button.setAttribute('aria-label', 'Afficher le mot de passe saisi');
        button.setAttribute('title', 'Afficher le mot de passe saisi');
        button.innerHTML = '<span class="dashicons dashicons-visibility" aria-hidden="true"></span>';
        button.addEventListener('click', function () {
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.setAttribute('aria-label', isHidden ? 'Masquer le mot de passe saisi' : 'Afficher le mot de passe saisi');
            button.setAttribute('title', isHidden ? 'Masquer le mot de passe saisi' : 'Afficher le mot de passe saisi');
            button.innerHTML = '<span class="dashicons ' + (isHidden ? 'dashicons-hidden' : 'dashicons-visibility') + '" aria-hidden="true"></span>';
        });
        wrapper.appendChild(button);
    });
}());
