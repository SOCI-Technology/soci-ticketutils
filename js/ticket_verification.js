window.addEventListener('load', () => {
    const dolData = window.dolData;

    if (!dolData) {
        console.warn('No dolData');
        throw new Error('Error');
    }

    initTicketVerification();
    initModals();
    initToggleButtons();
    initCheckVerification();

    function initTicketVerification() {
        const saveButton = document.querySelector('input[name="save"]');

        if (!saveButton) {
            console.warn('No save button');
            return;
        }

        saveButton.setAttribute('type', 'button');;

        saveButton.addEventListener('click', () => {
            saveButton.setAttribute('disabled', 'true');

            try {
                sendVerification();
            }
            catch (e) {
                alert(e.message);
                saveButton.removeAttribute('disabled');
            }
        })
    }

    function initModals() {
        const modalList = document.querySelectorAll('.ticket-verification-modal');

        /* for (const modal of modalList) {
            modal.addEventListener('click', (event) => {
                if (event.currentTarget == event.target) {
                    modal.style.display = 'none';
                }
            })
        } */
    }

    function initToggleButtons() {
        const toggleModalButtons = document.querySelectorAll(`.toggle-modal`);

        console.log(toggleModalButtons);

        for (const button of toggleModalButtons) {
            button.addEventListener('click', () => {
                const modalId = button.dataset.modalId;

                if (!modalId) {
                    console.warn('No modal id');
                    return;
                }

                toggleModal(modalId);
            })
        }
    }

    function toggleModal(modalId) {
        const modal = document.querySelector(`.ticket-verification-modal[data-modal-id="${modalId}"]`);

        if (!modal) {
            console.warn('No modal');
            return;
        }

        if (modal.style.display == 'none') {
            modal.style.display = 'flex';
        } else {
            modal.style.display = 'none';
        }
    }

    function verify_fields() {
        const errors = [];

        const email = document.getElementById('email')?.value;

        if (!email) {
            errors.push('Email');
        }

        const typeCode = document.getElementById('selecttype_code')?.value;

        if (!typeCode) {
            errors.push('TypeCode');
        }

        const subject = document.getElementById('subject')?.value;

        if (!subject) {
            errors.push('Subject');
        }

        const message = document.getElementById('message')?.value;

        if (!message) {
            errors.push('Message');
        }

        return errors;
    }

    function sendVerification() {
        const dolData = window.dolData;

        if (!dolData) {
            throw new Error('Error');
        }

        const translations = dolData.translations;

        const URL_ROOT = dolData.URL_ROOT;

        const url = `${URL_ROOT}/custom/ticketutils/lib/ajax/ticket_verification.ajax.php`;

        const errors = verify_fields();

        if (errors.length > 0) {
            let alertString = '';
            for (const error of errors) {
                alertString += translations[`errorTicket${error}`] + '\n';
            }

            throw new Error(alertString);
        }

        const email = document.getElementById('email')?.value;

        const formData = new FormData();
        
        formData.append('action', 'create_verification');
        formData.append('email', email);

        const xhr = new XMLHttpRequest();
        
        xhr.open('POST', url, true);

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                console.log('Success:', xhr.responseText);
                toggleModal('ticket-verification-modal');
            } else {
                console.error('Error:', xhr.statusText);
                alert(translations.errorCreatingTicketVerification);

                const saveButton = document.querySelector('input[name="save"]');
                if (saveButton) {
                    saveButton.removeAttribute('disabled');
                }
            }
        };
        xhr.onerror = function () {
            console.error('Request failed');
        };

        xhr.send(formData);
    }

    function initCheckVerification() {
        const submitButton = document.getElementById('submit-verification-code');

        if (!submitButton) {
            console.warn('No submit button');
            return;
        }

        submitButton.addEventListener('click', () => {
            checkVerificationCode();
        })
    }

    function checkVerificationCode() {
        const code = document.getElementById('verification-code')?.value;

        if (!code) {
            alert('No code');
            return;
        }

        const translations = dolData.translations;

        const URL_ROOT = dolData.URL_ROOT;

        const url = `${URL_ROOT}/custom/ticketutils/lib/ajax/ticket_verification.ajax.php`;

        const email = document.getElementById('email')?.value;

        const formData = new FormData();

        formData.append('action', 'check_verification');
        formData.append('email', email);
        formData.append('code', code);

        const xhr = new XMLHttpRequest();

        xhr.open('POST', url, true);

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                console.log('Success:', xhr.responseText);
                const response = JSON.parse(xhr.responseText);

                verificationResult(response.data);
            } else {
                console.error('Error:', xhr.statusText);
                alert(translations.errorCreatingTicketVerification);
            }
        };
        xhr.onerror = function () {
            console.error('Request failed');
        };

        xhr.send(formData);
    }

    function verificationResult(data) {
        if (data.result == 1) {
            submitTicket();
        }
        else {
            alert(dolData.translations.wrongCode);
        }
    }

    function submitTicket() {
        const form = document.getElementById('form_create_ticket');

        const saveInput = document.createElement('input');

        saveInput.setAttribute('type', 'hidden');
        saveInput.setAttribute('name', 'save');
        saveInput.setAttribute('value', '1');

        form.appendChild(saveInput);

        form.submit();
    }
})