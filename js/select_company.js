window.addEventListener('load', () => {
    initEmailInput();
    moveSelectCompany();

    function initEmailInput() {
        const emailInput = document.getElementById('email');

        if (!emailInput) {
            console.warn('No email input');
            return;
        }

        emailInput.addEventListener('change', () => onChangeEmailInput(emailInput));

        onChangeEmailInput(emailInput);
    }

    function onChangeEmailInput(emailInput) {
        const URL_ROOT = document.getElementById('URL_ROOT')?.value;

        if (!URL_ROOT) {
            console.warn('No URL_ROOT');
            return;
        }

        if (!emailInput.value) {
            console.warn('No email input value');
            updateCompanyOptions([]);
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.open('GET', `${URL_ROOT}/custom/ticketutils/ajax/search_email_companies.ajax.php?email=` + encodeURIComponent(emailInput.value), true);
        xhr.onload = () => {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);

                if (response.companies) {
                    updateCompanyOptions(response.companies);
                }
            }
        }
        xhr.send();
    }

    /**
     * 
     * @param {{id:string,name:string}[]} companyList 
     */
    function updateCompanyOptions(companyList) {
        const selectCompany = document.getElementById('select_company');

        if (!selectCompany) {
            console.warn('No select company');
            return;
        }

        selectCompany.innerHTML = '';

        for (const company of companyList) {
            const option = document.createElement('option');
            option.value = company.id;
            option.textContent = company.name;
            selectCompany.appendChild(option);
        }

        const selectCompanyRow = document.getElementById('select_company_row');

        if (selectCompanyRow) {
            if (companyList.length > 0) {
                selectCompanyRow.style.display = 'table-row';
            } else {
                selectCompanyRow.style.display = 'none';
            }
        }
    }

    function moveSelectCompany() {
        const selectCompanyRow = document.getElementById('select_company_row');

        if (!selectCompanyRow) {
            console.warn('No select company row');
            return;
        }

        const emailInput = document.getElementById('email');

        if (!emailInput) {
            console.warn('No email input');
            return;
        }

        const emailInputRow = emailInput.closest('tr');

        if (!emailInputRow) {
            console.warn('No email input row');
            return;
        }

        emailInputRow.after(selectCompanyRow);
    }
})