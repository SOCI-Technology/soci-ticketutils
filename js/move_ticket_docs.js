window.addEventListener('load', () => {
    const linkedElementsTable = document.querySelector('.centpercent.notopnoleftnoright.table-fiche-title.showlinkedobjectblock');

    if (!linkedElementsTable) {
        console.warn('No linked elements table');        
        
        return;
    }

    const ticketDocsContainer = document.getElementById('ticket_docs_container');

    if (!ticketDocsContainer) {
        console.warn('No ticket docs container');        
        return;
    }

    linkedElementsTable.before(ticketDocsContainer);
})