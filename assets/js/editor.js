/**
 * Hotspot Field editor: shows the Linked Image Field's image, adds a Hotspot
 * where the editor clicks it, and lists the Hotspots with their Label and
 * Description. A Hotspot is selected by its marker or its row; markers move by
 * dragging or with the arrow keys, and rows can be removed and reordered.
 * Moves, reorders and removals are announced in a polite live region. After a
 * failed save, rows whose Hotspot needs a Label say so. The list is
 * kept, as JSON, in the field's hidden input, which ACF saves with the rest of
 * the form.
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
			up: 'Up',
			down: 'Down',
			removeText: 'Remove',
			moveUp: 'Move %s up',
			moveDown: 'Move %s down',
			remove: 'Remove %s',
			position: '%1$s, %2$s across, %3$s down',
			reordered: '%1$s moved to position %2$s of %3$s.',
			removed: '%s removed.',
		},
		window.hotspotFieldEditor || {}
	);

	let uniqueId = 0;

	/**
	 * Fills in a translated string's numbered (%1$s) or plain (%s) placeholders.
	 *
	 * @param {string}    text   Translated string.
	 * @param {...string} values Values, in placeholder order.
	 * @return {string} Text.
	 */
	function format( text, ...values ) {
		let next = 0;
		return text.replace( /%(?:(\d+)\$)?[sd]/g, ( match, position ) => String( values[ position ? Number( position ) - 1 : next++ ] ?? '' ) );
	}

	/**
	 * Formats a position, in percent, for the page's language, such as "49.5%".
	 */
	const percent = new Intl.NumberFormat( document.documentElement.lang || undefined, { style: 'percent', maximumFractionDigits: 2 } );

	/**
	 * How long, in milliseconds, the live region waits for moves to stop
	 * before it announces where the Hotspot is, so a held arrow key is
	 * announced once.
	 */
	const ANNOUNCE_DELAY = 400;

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

	/**
	 * Keys that move the selected Hotspot, and the direction they move it in.
	 */
	const ARROW_KEYS = {
		ArrowLeft: [ -1, 0 ],
		ArrowRight: [ 1, 0 ],
		ArrowUp: [ 0, -1 ],
		ArrowDown: [ 0, 1 ],
	};

	/**
	 * How far, in percent, an arrow key moves a Hotspot; with Shift held.
	 */
	const STEP = 0.5;
	const LARGE_STEP = 5;

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
			this.drag = null;
			// Hotspots the last failed validation found without a Label. Kept
			// per Hotspot, not per row, so a row's message follows its Hotspot
			// when the list is reordered or a Hotspot is removed.
			this.missingLabel = new WeakSet();
			this.canvas = control.querySelector( '.hotspot-field-editor__canvas' );
			this.list = control.querySelector( '.hotspot-field-editor__list' );
			this.instructions = control.querySelector( '.hotspot-field-editor__instructions' );
			this.instructions.id = `hotspot-field-editor-${ ++uniqueId }-instructions`;
			// Focus lands here when the last Hotspot is removed.
			this.instructions.tabIndex = -1;

			// Announces moves, reorders and removals to screen readers.
			this.status = element( 'p', { className: 'hotspot-field-editor__status screen-reader-text' } );
			this.status.setAttribute( 'role', 'status' );
			this.status.setAttribute( 'aria-live', 'polite' );
			this.statusTimer = 0;
			control.append( this.status );

			this.image = element( 'img', { className: 'hotspot-field-editor__image', alt: strings.imageAlt, draggable: false } );
			this.markers = element( 'div', { className: 'hotspot-field-editor__markers' } );
			this.canvas.replaceChildren( this.image, this.markers );
			this.image.addEventListener( 'click', ( event ) => this.onImageClick( event ) );

			this.markers.addEventListener( 'focusin', ( event ) => this.onMarkerFocus( event ) );
			this.markers.addEventListener( 'keydown', ( event ) => this.onMarkerKeyDown( event ) );
			this.markers.addEventListener( 'pointerdown', ( event ) => this.onMarkerPointerDown( event ) );
			this.markers.addEventListener( 'pointermove', ( event ) => this.onMarkerPointerMove( event ) );
			this.markers.addEventListener( 'pointerup', ( event ) => this.onMarkerPointerUp( event ) );
			this.markers.addEventListener( 'pointercancel', ( event ) => this.onMarkerPointerCancel( event ) );

			this.list.addEventListener( 'input', ( event ) => this.onListInput( event ) );
			this.list.addEventListener( 'focusin', ( event ) => this.onRowActivate( event ) );
			this.list.addEventListener( 'click', ( event ) => this.onListClick( event ) );

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
		 * The position of a pointer, in percent of the image, clamped to it.
		 *
		 * @param {number} clientX Horizontal viewport coordinate.
		 * @param {number} clientY Vertical viewport coordinate.
		 * @return {?{x: number, y: number}} Position, or null while the image has no size.
		 */
		pointToPosition( clientX, clientY ) {
			const rect = this.image.getBoundingClientRect();
			if ( ! rect.width || ! rect.height ) {
				return null;
			}
			return {
				x: toPosition( ( ( clientX - rect.left ) / rect.width ) * 100 ),
				y: toPosition( ( ( clientY - rect.top ) / rect.height ) * 100 ),
			};
		},

		/**
		 * Adds a Hotspot where the image was clicked.
		 *
		 * @param {MouseEvent} event Click.
		 */
		onImageClick( event ) {
			const position = this.pointToPosition( event.clientX, event.clientY );
			if ( ! position ) {
				return;
			}

			this.hotspots.push( { ...position, label: '', description: '' } );
			this.selected = this.hotspots.length - 1;
			this.renderHotspots();
			this.save();

			this.rowAt( this.selected )?.querySelector( '.hotspot-field-editor__label' )?.focus();
		},

		/**
		 * The index of the Hotspot an element belongs to.
		 *
		 * @param {EventTarget} target Marker, row, or an element inside a row.
		 * @return {number} Index, or -1.
		 */
		indexOf( target ) {
			const owner = target instanceof Element ? target.closest( '[data-index]' ) : null;
			const index = owner ? Number( owner.dataset.index ) : -1;
			return index >= 0 && index < this.hotspots.length ? index : -1;
		},

		/**
		 * @param {number} index Hotspot index.
		 * @return {?HTMLElement} Marker.
		 */
		markerAt( index ) {
			return this.markers.children[ index ] || null;
		},

		/**
		 * @param {number} index Hotspot index.
		 * @return {?HTMLElement} List row.
		 */
		rowAt( index ) {
			return this.list.children[ index ] || null;
		},

		/**
		 * Selects a Hotspot: its marker and its row are marked as current.
		 *
		 * @param {number} index Hotspot index, or -1 for none.
		 */
		select( index ) {
			this.selected = index;
			[ ...this.markers.children, ...this.list.children ].forEach( ( node ) => {
				if ( Number( node.dataset.index ) === index ) {
					node.setAttribute( 'aria-current', 'true' );
				} else {
					node.removeAttribute( 'aria-current' );
				}
			} );
		},

		/**
		 * Selects the Hotspot whose marker receives focus.
		 *
		 * @param {FocusEvent} event Focus.
		 */
		onMarkerFocus( event ) {
			const index = this.indexOf( event.target );
			if ( index >= 0 ) {
				this.select( index );
			}
		},

		/**
		 * Moves the selected Hotspot with the arrow keys.
		 *
		 * @param {KeyboardEvent} event Key press.
		 */
		onMarkerKeyDown( event ) {
			if ( event.key === 'Escape' && this.drag ) {
				event.preventDefault();
				event.stopPropagation();
				this.cancelDrag();
				return;
			}

			const direction = ARROW_KEYS[ event.key ];
			const index = this.indexOf( event.target );
			if ( ! direction || index < 0 || event.altKey || event.ctrlKey || event.metaKey ) {
				return;
			}
			event.preventDefault();

			const step = event.shiftKey ? LARGE_STEP : STEP;
			const hotspot = this.hotspots[ index ];
			this.select( index );
			this.moveHotspot( index, hotspot.x + direction[ 0 ] * step, hotspot.y + direction[ 1 ] * step );
			this.save();
			this.announcePosition( index );
		},

		/**
		 * Starts dragging a marker.
		 *
		 * @param {PointerEvent} event Pointer down.
		 */
		onMarkerPointerDown( event ) {
			const index = this.markerUnder( event.clientX, event.clientY, this.indexOf( event.target ) );
			if ( index < 0 || event.button !== 0 ) {
				return;
			}
			// Keeps the browser from selecting text or starting a native drag.
			event.preventDefault();

			const marker = this.markerAt( index );
			marker.focus();
			this.select( index );

			// Keep the marker where it was grabbed, rather than centring it on
			// the pointer.
			const rect = marker.getBoundingClientRect();
			const hotspot = this.hotspots[ index ];
			this.drag = {
				index,
				pointerId: event.pointerId,
				startX: hotspot.x,
				startY: hotspot.y,
				offsetX: event.clientX - ( rect.left + rect.width / 2 ),
				offsetY: event.clientY - ( rect.top + rect.height / 2 ),
				moved: false,
			};
			marker.setPointerCapture?.( event.pointerId );
		},

		/**
		 * The marker to grab at a point. Each marker can be grabbed a little
		 * outside its visible edge, so a press near two close markers can land
		 * on the target of the one drawn on top while the pointer is over the
		 * other one: the marker that is visible under the pointer wins.
		 *
		 * @param {number} clientX Horizontal viewport coordinate.
		 * @param {number} clientY Vertical viewport coordinate.
		 * @param {number} index   Index of the marker that was hit, or -1.
		 * @return {number} Index of the marker to grab, or -1.
		 */
		markerUnder( clientX, clientY, index ) {
			if ( index < 0 ) {
				return index;
			}
			const markers = [ ...this.markers.children ];
			for ( let candidate = markers.length - 1; candidate >= 0; candidate-- ) {
				const rect = markers[ candidate ].getBoundingClientRect();
				if ( clientX >= rect.left && clientX <= rect.right && clientY >= rect.top && clientY <= rect.bottom ) {
					return candidate;
				}
			}
			return index;
		},

		/**
		 * Moves the dragged marker with the pointer.
		 *
		 * @param {PointerEvent} event Pointer move.
		 */
		onMarkerPointerMove( event ) {
			if ( ! this.drag || event.pointerId !== this.drag.pointerId ) {
				return;
			}
			const position = this.pointToPosition( event.clientX - this.drag.offsetX, event.clientY - this.drag.offsetY );
			if ( position ) {
				this.moveHotspot( this.drag.index, position.x, position.y );
				this.drag.moved = true;
			}
		},

		/**
		 * Ends a drag and saves where the marker was dropped.
		 *
		 * @param {PointerEvent} event Pointer up or cancel.
		 */
		onMarkerPointerUp( event ) {
			if ( ! this.drag || event.pointerId !== this.drag.pointerId ) {
				return;
			}
			const { index, moved } = this.drag;
			this.drag = null;
			if ( moved ) {
				this.save();
				this.announcePosition( index );
			}
		},

		/**
		 * Puts the marker back where it was when the browser cancels the drag
		 * (for example, to scroll).
		 *
		 * @param {PointerEvent} event Pointer cancel.
		 */
		onMarkerPointerCancel( event ) {
			if ( this.drag && event.pointerId === this.drag.pointerId ) {
				this.cancelDrag();
			}
		},

		/**
		 * Ends a drag without saving it: the marker goes back to where it was
		 * before the drag.
		 */
		cancelDrag() {
			const { index, pointerId, startX, startY } = this.drag;
			this.drag = null;
			const marker = this.markerAt( index );
			if ( marker?.hasPointerCapture?.( pointerId ) ) {
				marker.releasePointerCapture( pointerId );
			}
			this.moveHotspot( index, startX, startY );
		},

		/**
		 * Moves a Hotspot, keeping it on the image.
		 *
		 * @param {number} index Hotspot index.
		 * @param {number} x     Horizontal position, in percent.
		 * @param {number} y     Vertical position, in percent.
		 */
		moveHotspot( index, x, y ) {
			const hotspot = this.hotspots[ index ];
			hotspot.x = toPosition( x );
			hotspot.y = toPosition( y );
			const marker = this.markerAt( index );
			if ( marker ) {
				marker.style.left = `${ hotspot.x }%`;
				marker.style.top = `${ hotspot.y }%`;
			}
		},

		/**
		 * Selects the Hotspot whose row receives focus.
		 *
		 * @param {Event} event Focus or click.
		 */
		onRowActivate( event ) {
			const index = this.indexOf( event.target );
			if ( index >= 0 && index !== this.selected ) {
				this.select( index );
			}
		},

		/**
		 * Selects the clicked row and runs its Remove, Up and Down buttons.
		 *
		 * @param {MouseEvent} event Click.
		 */
		onListClick( event ) {
			const index = this.indexOf( event.target );
			if ( index < 0 ) {
				return;
			}

			const action = event.target.closest( '[data-action]' )?.dataset.action;
			if ( action === 'remove' ) {
				this.removeHotspot( index );
			} else if ( action === 'up' || action === 'down' ) {
				this.reorderHotspot( index, action === 'up' ? -1 : 1, action );
			} else {
				this.onRowActivate( event );
			}
		},

		/**
		 * Removes a Hotspot and moves focus to the row that takes its place.
		 *
		 * @param {number} index Hotspot index.
		 */
		removeHotspot( index ) {
			const name = this.hotspotName( index );
			this.hotspots.splice( index, 1 );
			const next = Math.min( index, this.hotspots.length - 1 );
			this.selected = next;
			this.renderHotspots();
			this.save();

			if ( next >= 0 ) {
				this.rowAt( next ).querySelector( '[data-action="remove"]' ).focus();
			} else {
				this.instructions.focus();
			}
			this.announce( format( strings.removed, name ) );
		},

		/**
		 * Moves a Hotspot one place up or down the list. Focus stays on the
		 * button that was used, or moves to the other one when that button is
		 * now disabled.
		 *
		 * @param {number} index  Hotspot index.
		 * @param {number} offset -1 for up, 1 for down.
		 * @param {string} action Button that was used: "up" or "down".
		 */
		reorderHotspot( index, offset, action ) {
			const target = index + offset;
			if ( target < 0 || target >= this.hotspots.length ) {
				return;
			}

			const name = this.hotspotName( index );
			const [ hotspot ] = this.hotspots.splice( index, 1 );
			this.hotspots.splice( target, 0, hotspot );
			this.selected = target;
			this.renderHotspots();
			this.save();

			const row = this.rowAt( target );
			const used = row.querySelector( `[data-action="${ action }"]` );
			( used.disabled ? row.querySelector( `[data-action="${ action === 'up' ? 'down' : 'up' }"]` ) : used ).focus();
			this.announce( format( strings.reordered, name, String( target + 1 ), String( this.hotspots.length ) ) );
		},

		/**
		 * Announces where a Hotspot is, such as "Hotspot 1: Lamp, 51% across,
		 * 49.5% down".
		 *
		 * @param {number} index Hotspot index.
		 */
		announcePosition( index ) {
			const hotspot = this.hotspots[ index ];
			if ( hotspot ) {
				this.announce( format( strings.position, this.hotspotName( index ), percent.format( hotspot.x / 100 ), percent.format( hotspot.y / 100 ) ) );
			}
		},

		/**
		 * Puts a message in the live region once things have been quiet for a
		 * moment; a newer message replaces one still waiting.
		 *
		 * @param {string} message Message.
		 */
		announce( message ) {
			window.clearTimeout( this.statusTimer );
			this.statusTimer = window.setTimeout( () => {
				this.status.textContent = message;
			}, ANNOUNCE_DELAY );
		},

		/**
		 * Keeps the list up to date while the editor types.
		 *
		 * @param {InputEvent} event Input.
		 */
		onListInput( event ) {
			const index = this.indexOf( event.target );
			const hotspot = this.hotspots[ index ];
			if ( ! hotspot ) {
				return;
			}

			if ( event.target.classList.contains( 'hotspot-field-editor__label' ) ) {
				hotspot.label = event.target.value;
				this.nameControls( index );
				if ( hotspot.label.trim() ) {
					this.missingLabel.delete( hotspot );
				}
				this.renderRowError( this.rowAt( index ) );
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
		 * The name of a Hotspot, for accessible names: its position in the
		 * list and its Label.
		 *
		 * @param {number} index Hotspot index.
		 * @return {string} Name.
		 */
		hotspotName( index ) {
			const name = format( strings.hotspot, String( index + 1 ) );
			const label = this.hotspots[ index ].label.trim();
			return label ? `${ name }: ${ label }` : name;
		},

		/**
		 * Names a Hotspot's marker and row buttons after the Hotspot.
		 *
		 * @param {number} index Hotspot index.
		 */
		nameControls( index ) {
			const name = this.hotspotName( index );
			this.markerAt( index )?.setAttribute( 'aria-label', name );
			const row = this.rowAt( index );
			if ( ! row ) {
				return;
			}
			const names = { remove: strings.remove, up: strings.moveUp, down: strings.moveDown };
			row.querySelectorAll( '[data-action]' ).forEach( ( button ) => {
				button.setAttribute( 'aria-label', format( names[ button.dataset.action ], name ) );
			} );
		},

		/**
		 * Draws the markers on the image and the rows of the list.
		 */
		renderHotspots() {
			const last = this.hotspots.length - 1;

			this.markers.replaceChildren(
				...this.hotspots.map( ( hotspot, index ) => {
					const marker = element( 'button', {
						type: 'button',
						className: 'hotspot-field-editor__marker',
						textContent: String( index + 1 ),
					} );
					marker.dataset.index = String( index );
					marker.setAttribute( 'aria-describedby', this.instructions.id );
					marker.style.left = `${ hotspot.x }%`;
					marker.style.top = `${ hotspot.y }%`;
					return marker;
				} )
			);

			this.list.replaceChildren(
				...this.hotspots.map( ( hotspot, index ) => {
					const id = `hotspot-field-editor-${ ++uniqueId }`;
					const row = element( 'li', { className: 'hotspot-field-editor__row' } );
					row.dataset.index = String( index );

					const heading = element( 'span', {
						className: 'hotspot-field-editor__row-title',
						textContent: format( strings.hotspot, String( index + 1 ) ),
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

					const button = ( action, text, disabled = false ) => {
						const node = element( 'button', { type: 'button', className: 'button button-small', textContent: text, disabled } );
						node.dataset.action = action;
						return node;
					};
					const actions = element( 'div', { className: 'hotspot-field-editor__row-actions' } );
					actions.append(
						button( 'up', strings.up, index === 0 ),
						button( 'down', strings.down, index === last ),
						button( 'remove', strings.removeText )
					);

					row.append(
						heading,
						element( 'label', { htmlFor: label.id, textContent: strings.label } ),
						label,
						error,
						element( 'label', { htmlFor: description.id, textContent: strings.description } ),
						description,
						actions
					);
					this.renderRowError( row );
					return row;
				} )
			);

			this.hotspots.forEach( ( hotspot, index ) => this.nameControls( index ) );
			this.select( this.selected < this.hotspots.length ? this.selected : -1 );
		},
	} );

	acf.registerFieldType( HotspotField );
} )( window.jQuery, window.acf );
