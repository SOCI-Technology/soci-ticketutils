window.addEventListener('load', () => {
    moveCharacterCount();
    initCharacterCount();
});

function moveCharacterCount() {
    const messageTextarea = document.getElementById('message');

    if (!messageTextarea) {
        console.warn('No message textarea');
        return;        
    }
    
    const characterCountContainer = document.getElementById('character_count');

    if (!characterCountContainer) {
        console.warn('No character count container');
        return;
    }

    messageTextarea.before(characterCountContainer);
}

function initCharacterCount() {
    console.log('initing');
    
    
    const messageTextarea = document.getElementById('message');

    if (!messageTextarea) {
        console.warn('No message textarea');
        return;        
    }
    
    messageTextarea.addEventListener('input', updateCharacterCount)

    //messageTextarea.setAttribute('required', true);
}

/**
 * 
 * @param {InputEvent} event 
 */
function updateCharacterCount(event) {
    const characterCountContainer = document.getElementById('character_count');

    const currentCountContainer = characterCountContainer.querySelector('#current_count');
    const maxCountContainer = characterCountContainer.querySelector('#max_count');

    if (!currentCountContainer || !maxCountContainer) {
        console.warn('No character count container');
        return;
    }

    const textarea = event.currentTarget;

    const currentCount = textarea.value.length;
    
    currentCountContainer.textContent = currentCount;
    currentCountContainer.dataset.value = currentCount;

    textarea.setAttribute('maxlength', maxCountContainer.dataset.value);
}