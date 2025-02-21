window.addEventListener('load', () => {
    hidePublicTrackId();
})

function hidePublicTrackId() {
    const titleFieldList = document.querySelectorAll('.titlefield');

    const trackIdLabel = document.getElementById('ticketutils_track_id_label')?.value;

    if (!trackIdLabel) {
        console.warn('trackIdLabel not found');

        return;
    }

    for (const titleField of titleFieldList) {
        if (titleField.textContent != trackIdLabel) {
            continue;
        }

        const parentRow = titleField.parentElement;

        if (parentRow) {
            parentRow.remove();
        }
    }
}