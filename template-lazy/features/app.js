/* template-lazy/features/app.js */
( function () {
    /* Stagger animate feature cards when section loads */
    if ( window.SLL ) {
        SLL.on( 'lazyLoaded', ( { el } ) => {
            if ( el.dataset.render !== 'features' ) return;
            const cards = el.querySelectorAll( '.sll-feature-card' );
            cards.forEach( ( card, i ) => {
                card.style.opacity   = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout( () => {
                    card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    card.style.opacity    = '1';
                    card.style.transform  = 'translateY(0)';
                }, i * 100 );
            } );
        } );
    }
} )();
