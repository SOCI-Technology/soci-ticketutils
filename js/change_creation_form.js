window.addEventListener('load', () => {
    changeCreationForm();

    function changeCreationForm() {
        initEntryTypeSelect();
    }

    function initEntryTypeSelect() {
        const entryTypeSelect = document.getElementById('entry_type');

        if (!entryTypeSelect) {
            console.warn('No entry type select');

            return;
        }

        entryTypeSelect.addEventListener('change', () => toggleFields(entryTypeSelect.value));

        const selectRow = entryTypeSelect.closest('tr');

        const titleFieldCreate = document.querySelector('.titlefieldcreate');

        if (!titleFieldCreate) {
            console.warn('No title field create');

            return;
        }

        const titleRow = titleFieldCreate.closest('tr');

        if (!titleRow) {
            console.warn('No title row');

            return;
        }

        titleRow.before(selectRow);
    }

    function toggleFields(entryType) {
        const makeVisible = entryType == 'external';

        const groupSelect = document.getElementById('selectcategory_code');

        const groupSelectRow = groupSelect?.closest('tr');

        if (groupSelectRow) {
            groupSelectRow.style.display = makeVisible ? 'table-row' : 'none';
        }

        const addedFile = document.getElementById('addedfile');

        const addedFileRow = addedFile?.closest('tr');

        let lastSibling = addedFileRow;

        while (lastSibling.nextElementSibling) {
            const sibling = lastSibling.nextElementSibling;

            if (!sibling.nodeName || sibling.nodeName != 'TR') {
                break;
            }

            sibling.style.display = makeVisible ? 'table-row' : 'none';

            lastSibling = sibling
        }
    }
});