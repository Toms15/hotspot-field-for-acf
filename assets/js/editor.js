/**
 * Hotspot Field editor: shows the Linked Image Field's image, adds a Hotspot
 * where the editor clicks it, and lists the Hotspots with their Label and
 * Description. The list is kept, as JSON, in the field's hidden input, which
 * ACF saves with the rest of the form.
 */
( function ( $, acf ) {
	'use strict';

	if ( ! acf ) {
		return;
	}

	const strings = Object.assign(
		{
			imageAlt: 'Image to place Hotspots on',
			hotspot: 'Hotspot %d',
			label: 'Label',
			description: 'Description',
			missingLabel: 'This Hotspot needs a Label.',
		},
		window.hotspotFieldEditor || {}
	);

	let uniqueId = 0;

	/**
	 * A position in percent, clamped to 0–100 and rounded to two decimals.
	 *
	 * @param {number} value Position.
	 * @return {number} Normalized position.
	 */
	function toPosition( value ) {
		return Math.round( Math.min( 100, Math.max( 0, value ) ) * 100 ) / 100;
	}

	/**
	 * Reads the list of Hotspots from the hidden input.
	 *
	 * @param {string} json Stored JSON.
	 * @return {Array<Object>} Hotspots.
	 */
	function parseHotspots( json ) {
		let hotspots;
		try {
			hotspots = JSON.parse( json || '[]' );
		} catch {
			return [];
		}
		if ( ! Array.isArray( hotspots ) ) {
			return [];
		}
		return hotspots
			.filter( ( hotspot ) => hotspot && Number.isFinite( Number( hotspot.x ) ) && Number.isFinite( Number( hotspot.y ) ) )
			.map( ( hotspot ) => ( {
				x: toPosition( Number( hotspot.x ) ),
				y: toPosition( Number( hotspot.y ) ),
				label: String( hotspot.label ?? '' ),
				description: String( hotspot.description ?? '' ),
			} ) );
	}

	/**
	 * Creates an element.
	 *
	 * @param {string} tag        Tag name.
	 * @param {Object} properties Properties to assign.
	 * @return {HTMLElement} Element.
	 */
	function element( tag, properties = {} ) {
		return Object.assign( document.createElement( tag ), properties );
	}

	const HotspotField = acf.Field.extend( {
		type: 'hotspot',

		$control() {
			return this.$( '.hotspot-field-editor' );
		},

		$input() {
			return this.$( '.hotspot-field-editor__value' );
		},

		initialize() {
			const control = this.$control().get( 0 );
			if ( ! control ) {
				return;
			}

			this.hotspots = parseHotspots( this.$input().val() );
			this.selected = -1;
			// Hotspots the last failed validation found without a Label.
			this.missingLabel = new WeakSet();
			this.canvas = control.querySelector( '.hotspot-field-editor__canvas' );
			this.list = control.querySelector( '.hotspot-field-editor__list' );

			this.image = element( 'img', { className: 'hotspot-field-editor__image', alt: strings.imageAlt, draggable: false } );
			this.markers = element( 'div', { className: 'hotspot-field-editor__markers' } );
			this.canvas.replaceChildren( this.image, this.markers );
			this.image.addEventListener( 'click', ( event ) => this.onImageClick( event ) );

			this.list.addEventListener( 'input', ( event ) => this.onListInput( event ) );

			this.loadImage( control.dataset.linkedImageField || '' );
			this.renderHotspots();
		},

		/**
		 * Shows the image chosen in the Linked Image Field of the same field
		 * group.
		 *
		 * @param {string} name Linked Image Field name.
		 */
		loadImage( name ) {
			const linked = name ? acf.getFields( { name, sibling: this.$el, limit: 1 } )[ 0 ] : null;
			if ( ! linked ) {
				return;
			}

			const preview = linked.$el.find( 'img[data-name="image"]' ).attr( 'src' );
			if ( preview ) {
				this.showImage( preview );
			}

			// The preview is usually small: replace it with a larger size.
			const attachmentId = parseInt( linked.val(), 10 );
			if ( attachmentId && window.wp?.media?.attachment ) {
				const attachment = window.wp.media.attachment( attachmentId );
				Promise.resolve( attachment.fetch() )
					.then( () => {
						const sizes = attachment.get( 'sizes' ) || {};
						const url = ( sizes.large || sizes.full || {} ).url || attachment.get( 'url' );
						if ( url ) {
							this.showImage( url );
						}
					} )
					// Keep the preview when the larger size cannot be fetched.
					.catch( () => {} );
			}
		},

		/**
		 * @param {string} url Image URL.
		 */
		showImage( url ) {
			this.image.src = url;
			this.canvas.hidden = false;
		},

		/**
		 * Adds a Hotspot where the image was clicked.
		 *
		 * @param {MouseEvent} event Click.
		 */
		onImageClick( event ) {
			const rect = this.image.getBoundingClientRect();
			if ( ! rect.width || ! rect.height ) {
				return;
			}

			this.hotspots.push( {
				x: toPosition( ( ( event.clientX - rect.left ) / rect.width ) * 100 ),
				y: toPosition( ( ( event.clientY - rect.top ) / rect.height ) * 100 ),
				label: '',
				description: '',
			} );
			this.selected = this.hotspots.length - 1;
			this.renderHotspots();
			this.save();

			this.list.querySelector( '.hotspot-field-editor__row.is-selected .hotspot-field-editor__label' )?.focus();
		},

		/**
		 * Keeps the list up to date while the editor types.
		 *
		 * @param {InputEvent} event Input.
		 */
		onListInput( event ) {
			const row = event.target.closest( '.hotspot-field-editor__row' );
			const hotspot = row && this.hotspots[ Number( row.dataset.index ) ];
			if ( ! hotspot ) {
				return;
			}

			if ( event.target.classList.contains( 'hotspot-field-editor__label' ) ) {
				hotspot.label = event.target.value;
				this.markers.children[ Number( row.dataset.index ) ]?.setAttribute( 'aria-label', this.markerName( Number( row.dataset.index ) ) );
				if ( hotspot.label.trim() ) {
					this.missingLabel.delete( hotspot );
				}
				this.renderRowError( row );
			} else if ( event.target.classList.contains( 'hotspot-field-editor__description' ) ) {
				hotspot.description = event.target.value;
			}
			this.save();
		},

		/**
		 * Shows ACF's validation message on the field and, on each row whose
		 * Hotspot has no Label, a message of its own until it gets one.
		 *
		 * @param {string} message  Message.
		 * @param {string} location Where ACF places the message.
		 */
		showError( message, location ) {
			acf.Field.prototype.showError.apply( this, [ message, location ] );
			if ( ! this.list ) {
				return;
			}

			this.hotspots.forEach( ( hotspot ) => {
				if ( ! hotspot.label.trim() ) {
					this.missingLabel.add( hotspot );
				}
			} );
			this.list.querySelectorAll( '.hotspot-field-editor__row' ).forEach( ( row ) => this.renderRowError( row ) );
		},

		/**
		 * Shows or hides the message of a row whose Hotspot needs a Label.
		 *
		 * @param {HTMLElement} row List row.
		 */
		renderRowError( row ) {
			const hotspot = this.hotspots[ Number( row.dataset.index ) ];
			const label = row.querySelector( '.hotspot-field-editor__label' );
			const error = row.querySelector( '.hotspot-field-editor__row-error' );
			const invalid = Boolean( hotspot && this.missingLabel.has( hotspot ) );

			row.classList.toggle( 'has-error', invalid );
			error.hidden = ! invalid;
			if ( invalid ) {
				label.setAttribute( 'aria-invalid', 'true' );
				label.setAttribute( 'aria-describedby', error.id );
			} else {
				label.removeAttribute( 'aria-invalid' );
				label.removeAttribute( 'aria-describedby' );
			}
		},

		/**
		 * Writes the list to the hidden input that ACF saves.
		 */
		save() {
			this.$input().val( JSON.stringify( this.hotspots ) ).trigger( 'change' );
		},

		/**
		 * The accessible name of a marker.
		 *
		 * @param {number} index Hotspot index.
		 * @return {string} Name.
		 */
		markerName( index ) {
			const name = strings.hotspot.replace( '%d', String( index + 1 ) );
			const label = this.hotspots[ index ].label.trim();
			return label ? `${ name }: ${ label }` : name;
		},

		/**
		 * Draws the markers on the image and the rows of the list.
		 */
		renderHotspots() {
			this.markers.replaceChildren(
				...this.hotspots.map( ( hotspot, index ) => {
					const marker = element( 'button', {
						type: 'button',
						className: 'hotspot-field-editor__marker' + ( index === this.selected ? ' is-selected' : '' ),
						textContent: String( index + 1 ),
					} );
					marker.setAttribute( 'aria-label', this.markerName( index ) );
					marker.style.left = `${ hotspot.x }%`;
					marker.style.top = `${ hotspot.y }%`;
					return marker;
				} )
			);

			this.list.replaceChildren(
				...this.hotspots.map( ( hotspot, index ) => {
					const id = `hotspot-field-editor-${ ++uniqueId }`;
					const row = element( 'li', {
						className: 'hotspot-field-editor__row' + ( index === this.selected ? ' is-selected' : '' ),
					} );
					row.dataset.index = String( index );

					const heading = element( 'span', {
						className: 'hotspot-field-editor__row-title',
						textContent: strings.hotspot.replace( '%d', String( index + 1 ) ),
					} );

					const label = element( 'input', {
						type: 'text',
						id: `${ id }-label`,
						className: 'hotspot-field-editor__label',
						value: hotspot.label,
					} );
					const error = element( 'p', {
						id: `${ id }-error`,
						className: 'hotspot-field-editor__row-error',
						textContent: strings.missingLabel,
					} );
					const description = element( 'textarea', {
						id: `${ id }-description`,
						className: 'hotspot-field-editor__description',
						rows: 3,
						value: hotspot.description,
					} );

					row.append(
						heading,
						element( 'label', { htmlFor: label.id, textContent: strings.label } ),
						label,
						error,
						element( 'label', { htmlFor: description.id, textContent: strings.description } ),
						description
					);
					this.renderRowError( row );
					return row;
				} )
			);
		},
	} );

	acf.registerFieldType( HotspotField );
} )( window.jQuery, window.acf );
