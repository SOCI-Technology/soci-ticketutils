window.addEventListener('load', () => {
    const ratingItems = document.querySelectorAll('.rating-item');
    const ratingInput = document.getElementById('rating');
    const ratingContainer = document.querySelector('.rating-container');

    initTicketRating();

    function initTicketRating() {
        for (const ratingItem of ratingItems) {
            ratingItem.addEventListener('mouseover', hoverRating);
            ratingItem.addEventListener('click', clickRating);
        }

        ratingContainer.addEventListener('mouseout', exitRating);
    }

    function hoverRating(event) {
        const rating = event.currentTarget.dataset.value;

        for (const ratingItem of ratingItems) {
            if (ratingItem.dataset.value <= rating) {
                ratingItem.classList.add('active');
            } else {
                ratingItem.classList.remove('active');
            }
        }
    }

    function exitRating(event) {
        selectRating(ratingInput.value);
    }
    
    function clickRating(event) {
        const rating = event.currentTarget.dataset.value;

        selectRating(rating);
    }
    
    function selectRating(rating) {
        for (const ratingItem of ratingItems) {
            if (ratingItem.dataset.value <= rating) {
                ratingItem.classList.add('active');
            } else {
                ratingItem.classList.remove('active');
            }
        }
        
        ratingInput.value = rating;
    }
});
