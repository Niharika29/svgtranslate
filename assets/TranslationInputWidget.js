/**
 * Translation input widget.
 *
 * @class
 * @extends OO.ui.TextInputWidget
 * @constructor
 * @param {Object} [config] Configuration options
 */
App.TranslationInputWidget = function AppTranslationInputWidget( config ) {
	var targetLangWidget, storedTranslations;
	App.TranslationInputWidget.parent.call( this, config );

	this.localStorageKey = 'svgtranslate-translations';

	// Determine the current target language code.
	targetLangWidget = OO.ui.infuse( $( '.target-lang-widget' ) );
	this.targetLangCode = targetLangWidget.getValue();

	// When creating (infusing) this widget on page-load,
	// see if there is a saved value in LocalStorage and if there is, use it.
	storedTranslations = JSON.parse( global.localStorage.getItem( this.localStorageKey ) );
	if ( storedTranslations !== null &&
		storedTranslations[ this.targetLangCode ] !== undefined &&
		storedTranslations[ this.targetLangCode ][ this.getElementId() ] !== undefined
	) {
		this.setValue( storedTranslations[ this.targetLangCode ][ this.getElementId() ] );
	}

	this.connect( this, { change: 'onChange' } );
};

// Inheritance.
OO.inheritClass( App.TranslationInputWidget, OO.ui.TextInputWidget );

/**
 * Change event handler. Save new value to LocalStorage.
 * @param {string} newValue This input's new value.
 */
App.TranslationInputWidget.prototype.onChange = function ( newValue ) {
	var stored = JSON.parse( global.localStorage.getItem( 'svgtranslate-translations' ) );
	if ( stored === null ) {
		stored = {};
	}
	if ( stored[ this.targetLangCode ] === undefined ) {
		stored[ this.targetLangCode ] = {};
	}
	stored[ this.targetLangCode ][ this.getElementId() ] = newValue;
	global.localStorage.setItem( 'svgtranslate-translations', JSON.stringify( stored ) );
};
