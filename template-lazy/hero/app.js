/* template-lazy/hero/app.js */
/* Runs once after the hero section is injected into the DOM */
( function () {
    const hero = document.querySelector( '.sll-hero' );
    if ( ! hero ) return;

    // Simple entrance animation triggered via SLL event
    if ( window.SLL ) {
        SLL.on( 'lazyLoaded', ( { el } ) => {
            if ( el.dataset.render === 'hero' ) {
                el.style.opacity = '0';
                requestAnimationFrame( () => {
                    el.style.transition = 'opacity 0.5s ease';
                    el.style.opacity    = '1';
                } );
            }
        } );
    }
} )();
