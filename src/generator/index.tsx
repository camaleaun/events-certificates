/**
 * The Generator screen's form (Certificates > Generator), in the look of the block editor's
 * Document panel: the event, the signature and the people as rows (a label and a value that opens
 * a popover with the field), the people as a list with the three downloads of the public page,
 * the tutorial as a drawer, and the bar at the top opening the command palette. Each control also
 * writes its value to a hidden input of the server-rendered <form> (see includes/generator.php),
 * which posts the event, its people and their verifiable certificates.
 */
import { speak } from '@wordpress/a11y';
import apiFetch from '@wordpress/api-fetch';
import {
	BaseControl,
	Button,
	DateTimePicker,
	DropZone,
	Dropdown,
	__experimentalHStack as HStack,
	MenuGroup,
	MenuItem,
	Modal,
	Panel,
	PanelBody,
	TabPanel,
	TextareaControl,
	TextControl,
	ToggleControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { store as commandsStore, useCommand } from '@wordpress/commands';
import { dispatch } from '@wordpress/data';
import { getSettings as getDateSettings } from '@wordpress/date';
import { createContext, createInterpolateElement, createPortal, createRoot, useContext, useEffect, useRef, useState } from '@wordpress/element';
import { __, _x, sprintf } from '@wordpress/i18n';
import { calendar, closeSmall, cog, download, help as helpIcon, page } from '@wordpress/icons';
import type { Design, SvgEl, SvgPath } from '../editor/model';
import { ImageRow, pictureSrc } from './picture-field';
import { drawPreview, drawingSrc, type PreviewConfig } from '../quick-generator/preview';
import { countSelected, mergePeople, selectedPeople, setAll, whatIsMissing } from './people';
import type { Incoming, Person } from './people';

declare global {
	interface Window {
		evcertGenerator?: {
			signerName?: string;
			signerRole?: string;
			tutorial?: Tutorial;
			quick?: PreviewConfig;
			signature?: string;
			signatureImage?: string;
			examples?: Record< string, string >;
			urls?: Record< string, string >;
		};
	}
}

// One step's drawing: the markup of the block (server-side, already filtered), and a short description.
type Picture = { html: string; alt: string };
type Tutorial = { title: string; intro: string; hint: string; steps: Array< { title: string; text: string; picture: Picture } > };
type Format = 'zip' | 'pdf';
type Answer = { people: Incoming[]; info: { rsvp_no: number; absent: number } };

const config = window.evcertGenerator || {};
const field = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

type Values = Record< string, string >;

function Hidden( { name, value }: { name: string; value: string } ) {
	return <input type="hidden" name={ name } value={ value } />;
}

const openHelp = () => window.dispatchEvent( new Event( 'evcert:open-help' ) );

// The header's right side, where the downloads sit (printed by the server, filled from the form).
const actionsRoot = document.getElementById( 'evcert-generator-actions' );

// Only one row's popover is open at a time: opening one closes the others.
const OpenRow = createContext< { open: string; setOpen: ( label: string ) => void } >( { open: '', setOpen: () => {} } );

// A row of the panel, like the Document panel's Status or Publish: the label and, as a quiet button, the value;
// a click opens a popover with the field.
function SummaryRow( {
	label,
	value,
	placeholder,
	action,
	disabled,
	children,
}: {
	label: string;
	value: string;
	placeholder?: string;
	action?: { label: string; onClick: () => void };
	// Why the value cannot be changed yet (the row is dimmed and says it).
	disabled?: string;
	children: React.ReactNode | ( ( close: () => void ) => React.ReactNode );
} ) {
	// The popover is anchored to the middle of the row, so it does not move when the value changes (as the editor's rows do).
	const [ anchor, setAnchor ] = useState< HTMLDivElement | null >( null );
	const rows = useContext( OpenRow );
	return (
		<div className="evcert-summary__row" ref={ setAnchor }>
			<span className="evcert-summary__label">{ label }</span>
			<Dropdown
				className="evcert-summary__dropdown"
				popoverProps={ { anchor, 'aria-label': label, placement: 'left-start', offset: 36, shift: true } }
				focusOnMount
				open={ rows.open === label }
				onToggle={ ( opening: boolean ) => rows.setOpen( opening ? label : rows.open === label ? '' : rows.open ) }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						variant="tertiary"
						size="compact"
						className="evcert-summary__value"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						disabled={ !! disabled }
						label={ disabled || undefined }
						showTooltip={ !! disabled }
						aria-label={ sprintf( /* translators: 1: a field's name, 2: its value. */ __( '%1$s: %2$s', 'events-certificates' ), label, value || placeholder || '' ) }
					>
						{ value ? value : <span className="evcert-summary__empty">{ placeholder || '—' }</span> }
					</Button>
				) }
				renderContent={ ( { onClose } ) => (
					<div className="evcert-summary__editor">
						<HStack className="evcert-summary__editor-header" alignment="center">
							<h2 className="evcert-summary__editor-title">{ label }</h2>
							{ action && (
								<Button size="small" variant="tertiary" onClick={ action.onClick }>
									{ action.label }
								</Button>
							) }
							<Button size="small" icon={ closeSmall } label={ __( 'Close' ) } onClick={ onClose } />
						</HStack>
						{ 'function' === typeof children ? children( onClose ) : children }
					</div>
				) }
			/>
		</div>
	);
}

// The CSV file: a full-width button with a short label (the file's name under it), and a drop zone.
// The file is read at once by the route, which fills the list.
function CsvField( { file, busy, onFile }: { file: string; busy: boolean; onFile: ( file: File ) => void } ) {
	const input = useRef< HTMLInputElement >( null );
	return (
		<BaseControl
			{ ...field }
			id="evcert-csv"
			label={ __( 'Participant list file', 'events-certificates' ) }
			help={ createInterpolateElement(
				__( 'Not sure where to download the list? <a>See the step by step on Meetup</a>.', 'events-certificates' ),
				{ a: <Button variant="link" onClick={ openHelp } /> }
			) }
		>
			<div className="evcert-generator__file">
				<Button variant="secondary" className="evcert-generator__file-toggle" disabled={ busy } onClick={ () => input.current?.click() }>
					{ file ? __( 'Replace the file', 'events-certificates' ) : __( 'Choose a CSV file', 'events-certificates' ) }
				</Button>
				{ file && <p className="evcert-generator__file-name">{ file }</p> }
				<DropZone onFilesDrop={ ( files ) => files[ 0 ] && onFile( files[ 0 ] ) } />
				<input
					type="file"
					id="evcert-csv"
					ref={ input }
					accept=".csv,text/csv"
					hidden
					onChange={ ( e ) => {
						const first = e.target.files?.[ 0 ];
						if ( first ) {
							onFile( first );
						}
						e.target.value = '';
					} }
				/>
			</div>
		</BaseControl>
	);
}

// The tutorial, in the drawer: the same steps, texts and pictures as the generator page, one step open at a time
// (opening one closes the other); a click on a picture enlarges it in a modal.
function TutorialPanel( { tutorial }: { tutorial: Tutorial } ) {
	const [ big, setBig ] = useState< Picture | null >( null );
	const [ open, setOpen ] = useState( 0 );
	return (
		<div className="evcert-tutorial">
			<div className="evcert-tutorial__head">
				<h2>{ tutorial.title }</h2>
				<Button icon={ closeSmall } label={ __( 'Close' ) } onClick={ () => window.dispatchEvent( new Event( 'evcert:close-help' ) ) } />
			</div>
			<p
				className="evcert-tutorial__intro"
				onClick={ ( event ) => {
					// The link of the intro brings the sidebar, already on its Participants tab.
					const link = ( event.target as HTMLElement ).closest( 'a[data-evcert-open]' );
					if ( link ) {
						event.preventDefault();
						window.dispatchEvent( new Event( 'evcert:toggle-people' ) );
					}
				} }
				dangerouslySetInnerHTML={ { __html: tutorial.intro } }
			/>
			<Panel className="evcert-tutorial__steps">
				{ tutorial.steps.map( ( step, i ) => (
					<PanelBody key={ step.title } title={ step.title } opened={ open === i } onToggle={ () => setOpen( open === i ? -1 : i ) }>
						<p className="evcert-tutorial__text" dangerouslySetInnerHTML={ { __html: step.text } } />
						{ /* The drawing is a button of its own (it opens the enlarged view on the front); here a click on it opens the modal. */ }
						<div
							className="evcert-tutorial__shot"
							onClick={ ( event ) => {
								if ( ( event.target as HTMLElement ).closest( '.evcert-tsi__open' ) ) {
									setBig( step.picture );
								}
							} }
							dangerouslySetInnerHTML={ { __html: step.picture.html } }
						/>
					</PanelBody>
				) ) }
			</Panel>
			<p className="evcert-tutorial__hint">{ tutorial.hint }</p>
			{ big && (
				<Modal title={ big.alt } size="large" className="evcert-tutorial-zoom" onRequestClose={ () => setBig( null ) }>
					<div className="evcert-tutorial-zoom__picture" dangerouslySetInnerHTML={ { __html: big.html } } />
				</Modal>
			) }
		</div>
	);
}

// The event's title, asked as the editor asks a post's name (Rename: a name, Cancel and Save).
function RenameModal( { title, onSave, onClose }: { title: string; onSave: ( title: string ) => void; onClose: () => void } ) {
	const [ name, setName ] = useState( title );
	return (
		<Modal title={ title ? __( 'Rename event', 'events-certificates' ) : __( 'Name event', 'events-certificates' ) } size="small" onRequestClose={ onClose }>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					onSave( name.trim() );
				} }
			>
				<VStack spacing={ 5 }>
					<TextControl { ...field } label={ __( 'Name' ) } value={ name } placeholder={ __( 'Event name…', 'events-certificates' ) } onChange={ setName } />
					<HStack justify="flex-end">
						<Button variant="tertiary" onClick={ onClose }>
							{ __( 'Cancel' ) }
						</Button>
						<Button variant="primary" type="submit">
							{ __( 'Save' ) }
						</Button>
					</HStack>
				</VStack>
			</form>
		</Modal>
	);
}

// The commands of this screen in the command palette (the bar at the top opens it).
// The palette lists the commands of its current context ('root' until a screen sets another) under
// Suggestions as soon as it opens; the context hook is a private API, so the commands use 'root'.
const CONTEXT = 'root';

function Commands( { canMany, canOne, helpOpen }: { canMany: boolean; canOne: boolean; helpOpen: boolean } ) {
	const urls = config.urls || {};
	const go = ( key: string ) => ( { close }: { close: () => void } ) => {
		close();
		if ( urls[ key ] ) {
			window.location.href = urls[ key ];
		}
	};
	const send = ( format: Format, current = false ) => ( { close }: { close: () => void } ) => {
		close();
		window.dispatchEvent( new CustomEvent( 'evcert:submit', { detail: { format, current } } ) );
	};
	useCommand( {
		name: 'evcert/generator-help',
		label: helpOpen ? __( 'Hide how to get participants', 'events-certificates' ) : __( 'Show how to get participants', 'events-certificates' ),
		icon: helpIcon,
		category: 'command',
		context: CONTEXT,
		callback: ( { close } ) => {
			close();
			window.dispatchEvent( new Event( helpOpen ? 'evcert:close-help' : 'evcert:open-help' ) );
		},
	} );
	useCommand( { name: 'evcert/generator-people', label: __( 'Show the participants', 'events-certificates' ), icon: page, category: 'command', context: CONTEXT, callback: ( { close } ) => { close(); window.dispatchEvent( new Event( 'evcert:toggle-people' ) ); } } );
	useCommand( { name: 'evcert/generator-current', label: __( 'Download current', 'events-certificates' ), icon: download, category: 'command', context: CONTEXT, callback: send( 'pdf', true ), disabled: ! canOne } );
	useCommand( { name: 'evcert/generator-pdf', label: __( 'Download PDF', 'events-certificates' ), icon: download, category: 'command', context: CONTEXT, callback: send( 'pdf' ), disabled: ! canMany } );
	useCommand( { name: 'evcert/generator-zip', label: __( 'Download ZIP', 'events-certificates' ), icon: download, category: 'command', context: CONTEXT, callback: send( 'zip' ), disabled: ! canMany } );
	useCommand( { name: 'evcert/generator-events', label: __( 'Go to Events', 'events-certificates' ), icon: calendar, category: 'command', context: CONTEXT, callback: go( 'events' ), disabled: ! urls.events } );
	useCommand( { name: 'evcert/generator-certificates', label: __( 'Go to Certificates', 'events-certificates' ), icon: page, category: 'command', context: CONTEXT, callback: go( 'certificates' ), disabled: ! urls.certificates } );
	useCommand( { name: 'evcert/generator-settings', label: __( 'Go to Settings', 'events-certificates' ), icon: cog, category: 'command', context: CONTEXT, callback: go( 'settings' ), disabled: ! urls.settings } );
	return null;
}

// As the editor does: 12-hour time when the site's time format has an unescaped "a" (AM/PM).
const is12HourTime = /a(?!\\)/i.test( getDateSettings().formats.time.toLowerCase().replace( /\\\\/g, '' ).split( '' ).reverse().join( '' ) );

const pad = ( n: number ) => String( n ).padStart( 2, '0' );

const todayLocal = () => {
	const now = new Date();
	return now.getFullYear() + '-' + pad( now.getMonth() + 1 ) + '-' + pad( now.getDate() );
};

// The start as a person reads it: "Nov 25, 2026, 7:00 PM" (the date alone while there is no time).
const startText = ( day: string, time: string ) => {
	if ( ! day ) {
		return '';
	}
	const shown = new Date( day + 'T' + ( time || '00:00' ) + ':00' );
	if ( isNaN( shown.getTime() ) ) {
		return day;
	}
	return shown.toLocaleString( document.documentElement.lang || undefined, { dateStyle: 'medium', ...( time ? { timeStyle: 'short' } : {} ) } );
};

// The end, from the start and a length in minutes ('' when there is no start time, or it would pass midnight).
const addMinutes = ( time: string, minutes: number ) => {
	const [ h, m ] = time.split( ':' ).map( Number );
	if ( isNaN( h ) || isNaN( m ) || h * 60 + m + minutes >= 24 * 60 ) {
		return '';
	}
	const total = h * 60 + m + minutes;
	return pad( Math.floor( total / 60 ) ) + ':' + pad( total % 60 );
};

// The lengths the End row offers, in minutes.
const LENGTHS: Array< [ number, string ] > = [
	[ 60, __( '1 hour', 'events-certificates' ) ],
	[ 90, __( '1.5 hours', 'events-certificates' ) ],
	[ 120, __( '2 hours', 'events-certificates' ) ],
	[ 180, __( '3 hours', 'events-certificates' ) ],
];

function Form() {
	const [ values, setValues ] = useState< Values >( {
		community: '',
		title: '',
		date: '',
		start_time: '',
		end_time: '',
		logo: '',
		logo_svg: '',
		signature_image: '',
		signature_svg: '',
		venue: '',
		city: '',
		speakers: '',
		link: '',
		signer_name: config.signerName || '',
		signer_role: config.signerRole || '',
		lines: '',
	} );
	// The end is a length from the start (2 hours at first) or a time chosen by hand.
	const [ endMode, setEndMode ] = useState< number | 'custom' >( 120 );
	const [ showHours, setShowHours ] = useState( true );
	const [ people, setPeople ] = useState< Person[] >( [] );
	const [ current, setCurrent ] = useState< number | null >( null );
	const [ file, setFile ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ note, setNote ] = useState( '' );
	// The sidebar: open or closed (the header's Settings toggle and its own X), and the tab shown.
	const [ open, setOpen ] = useState( true );
	const [ tab, setTab ] = useState( 'certificate' );
	const [ tabNonce, setTabNonce ] = useState( 0 );
	const [ openRow, setOpenRow ] = useState( '' );
	const [ adding, setAdding ] = useState( false );
	const [ renaming, setRenaming ] = useState( false );
	// Whether the drawer (how to get the participants) is open: the drawer's own script says when it changes.
	const [ helpOpen, setHelpOpen ] = useState( true );
	useEffect( () => {
		const onChange = ( e: Event ) => setHelpOpen( !! ( e as CustomEvent ).detail?.open );
		window.addEventListener( 'evcert:help-changed', onChange );
		return () => window.removeEventListener( 'evcert:help-changed', onChange );
	}, [] );
	const [ posting, setPosting ] = useState< { format: Format; rows: Person[] } | null >( null );
	const nextId = useRef( 1 );
	const set = ( key: string ) => ( value: string ) => setValues( ( old ) => ( { ...old, [ key ]: value } ) );
	const examples = config.examples || {};

	const currentPerson = people.find( ( p ) => p.id === current ) || null;
	const selected = countSelected( people );
	const endTime = 'custom' === endMode ? values.end_time : values.start_time ? addMinutes( values.start_time, endMode ) : '';
	const missingAll = whatIsMissing( values, selected, !! currentPerson, false );
	const missingOne = whatIsMissing( values, selected, !! currentPerson, true );

	// What is still missing, in the front page's words.
	const needText = ( missing: string[] ) => {
		const parts = [ ...( missing.includes( 'title' ) ? [ __( 'the title', 'events-certificates' ) ] : [] ), ...( missing.includes( 'date' ) ? [ __( 'the date', 'events-certificates' ) ] : [] ) ];
		const sentences = [];
		if ( parts.length ) {
			sentences.push( sprintf( __( 'Fill in %s of the meetup before generating.', 'events-certificates' ), parts.join( ' ' + __( 'and', 'events-certificates' ) + ' ' ) ) );
		}
		if ( missing.includes( 'people' ) || missing.includes( 'current' ) ) {
			sentences.push( __( 'Select at least one person.', 'events-certificates' ) );
		}
		return sentences.join( ' ' );
	};

	const update = ( id: number, change: Partial< Person > ) => setPeople( ( list ) => list.map( ( p ) => ( p.id === id ? { ...p, ...change } : p ) ) );

	// The typed names and the file go to the route, which reads them like the server does when it issues.
	const addPeople = async ( lines: string, csv?: File ) => {
		const body = new FormData();
		body.append( 'lines', lines );
		if ( csv ) {
			body.append( 'csv', csv );
		}
		setBusy( true );
		try {
			const answer = ( await apiFetch( { path: '/evcert/v1/generator/people', method: 'POST', body } ) ) as Answer;
			const merged = mergePeople( people, answer.people, nextId.current );
			nextId.current = merged.nextId;
			setPeople( merged.people );
			if ( ! currentPerson && merged.people.length ) {
				setCurrent( merged.people[ 0 ].id );
			}
			if ( csv ) {
				setFile( csv.name );
			} else {
				set( 'lines' )( '' );
			}
			const said: string[] = [ csv ? sprintf( __( '%d people imported', 'events-certificates' ), merged.added ) : sprintf( __( '%d name(s) added.', 'events-certificates' ), merged.added ) ];
			if ( answer.info.rsvp_no ) {
				said.push( sprintf( __( '%d with RSVP "No" left out', 'events-certificates' ), answer.info.rsvp_no ) );
			}
			if ( answer.info.absent ) {
				said.push( sprintf( __( '%d marked as absent were left unchecked', 'events-certificates' ), answer.info.absent ) );
			}
			if ( ! merged.added && ! answer.people.length ) {
				said.push( csv ? __( 'No names found in that file. Check that it is the Meetup attendee list.', 'events-certificates' ) : __( 'Add at least one name, or a CSV file with the participants.', 'events-certificates' ) );
			}
			setNote( said.join( ' ' ) );
			speak( said.join( ' ' ) );
		} catch ( error ) {
			const message = ( error as { message?: string } ).message || __( 'Could not read that file. Export it from Meetup again, or save it as .csv.', 'events-certificates' );
			setNote( message );
			speak( message, 'assertive' );
		} finally {
			setBusy( false );
		}
	};

	// A download: the form is posted with the people chosen; the server answers with the file, the page stays.
	const submit = ( format: Format, rows: Person[], justOne: boolean ) => {
		const missing = whatIsMissing( values, countSelected( rows.map( ( p ) => ( { ...p, on: true } ) ) ), rows.length > 0, justOne );
		if ( missing.length || ! rows.length ) {
			const text = needText( missing.length ? missing : [ 'people' ] );
			setNote( text );
			speak( text, 'assertive' );
			return;
		}
		setPosting( { format, rows } );
	};
	const latest = useRef( { submit, people, currentPerson } );
	latest.current = { submit, people, currentPerson };

	useEffect( () => {
		if ( ! posting ) {
			return;
		}
		( document.getElementById( 'evcert-generator-form' ) as HTMLFormElement | null )?.requestSubmit();
		// A file download fires no event on the page: the buttons come back after a moment.
		const timer = window.setTimeout( () => setPosting( null ), 2500 );
		return () => window.clearTimeout( timer );
	}, [ posting ] );

	// The header's Settings toggle opens and closes the sidebar; the palette can bring the participants tab.
	useEffect( () => {
		const settings = document.getElementById( 'evcert-generator-settings' );
		const onSettings = () => setOpen( ( now ) => ! now );
		const onPeople = () => {
			setTab( 'people' );
			setTabNonce( ( n ) => n + 1 );
			setOpen( true );
		};
		settings?.addEventListener( 'click', onSettings );
		window.addEventListener( 'evcert:toggle-people', onPeople );
		return () => {
			settings?.removeEventListener( 'click', onSettings );
			window.removeEventListener( 'evcert:toggle-people', onPeople );
		};
	}, [] );
	useEffect( () => {
		document.getElementById( 'evcert-generator-settings' )?.setAttribute( 'aria-pressed', open ? 'true' : 'false' );
		document.getElementById( 'evcert-generator-sidebar' )?.classList.toggle( 'is-closed', ! open );
	}, [ open ] );

	useEffect( () => {
		const onSubmit = ( e: Event ) => {
			const { submit: send, people: list, currentPerson: one } = latest.current;
			const { format, current } = ( e as CustomEvent ).detail;
			send( format, current ? ( one ? [ one ] : [] ) : selectedPeople( list ), !! current );
		};
		window.addEventListener( 'evcert:submit', onSubmit );
		return () => window.removeEventListener( 'evcert:submit', onSubmit );
	}, [] );

	// The certificate on the canvas follows the form: drawn here, in the browser, from the site's default design (as the public generator does).
	const canvasRef = useRef< HTMLCanvasElement | null >( null );
	const draw = useRef< () => void >( () => {} );
	draw.current = () => {
		const canvas = canvasRef.current;
		if ( ! canvas || ! config.quick ) {
			return;
		}
		const wide = canvas.parentElement?.clientWidth || 800;
		const dpr = Math.min( window.devicePixelRatio || 1, 2 );
		const page = config.quick.design.page;
		canvas.width = Math.round( wide * dpr );
		canvas.height = Math.round( ( wide * dpr * page.h ) / page.w );
		const signature = values.signature_svg ? drawingSrc( values.signature_svg ) : pictureSrc( values.signature_image ) || ( config.signature ? drawingSrc( config.signature ) : config.signatureImage || '' );
		drawPreview(
			canvas,
			config.quick,
			{ title: values.title, community: values.community, venue: values.venue, city: values.city, speakers: values.speakers, link: values.link, date: values.date, start: values.start_time, end: endTime, showHours, signName: values.signer_name || config.signerName || '', signRole: values.signer_role || config.signerRole || '' },
			currentPerson?.name || '',
			{ logo: values.logo_svg ? drawingSrc( values.logo_svg ) : pictureSrc( values.logo ), signature }
		);
	};
	useEffect( () => {
		const sheet = document.querySelector( '.evcert-generator__sheet' );
		if ( ! sheet || ! config.quick ) {
			return;
		}
		// The server's sample stays when there is nothing to draw with; else the canvas takes its place.
		const canvas = document.createElement( 'canvas' );
		canvas.className = 'evcert-generator__canvas-preview';
		canvas.setAttribute( 'role', 'img' );
		canvas.setAttribute( 'aria-label', __( 'Certificate preview', 'events-certificates' ) );
		sheet.replaceChildren( canvas );
		canvasRef.current = canvas;
		const watch = new ResizeObserver( () => draw.current() );
		watch.observe( sheet );
		return () => watch.disconnect();
	}, [] );
	useEffect( () => {
		const frame = window.requestAnimationFrame( () => draw.current() );
		return () => window.cancelAnimationFrame( frame );
	}, [ values, endTime, showHours, currentPerson?.name ] );

	const row = ( key: string, label: string, extra: Record< string, unknown > = {}, shown?: string ) => (
		<SummaryRow key={ key } label={ label } value={ shown ?? values[ key ] } placeholder={ ( extra.placeholder as string ) || undefined }>
			<TextControl { ...field } label={ label } hideLabelFromVision value={ values[ key ] } onChange={ set( key ) } { ...extra } />
		</SummaryRow>
	);

	const working = busy || !! posting;
	const need = needText( missingAll );
	const needOne = needText( missingOne );

	return (
		<OpenRow.Provider value={ { open: openRow, setOpen: setOpenRow } }>
		<Panel>
			<Commands canMany={ 0 === missingAll.length } canOne={ 0 === missingOne.length } helpOpen={ helpOpen } />
			<TabPanel
					key={ tabNonce }
					className="evcert-generator__tabs"
					initialTabName={ tab }
					onSelect={ setTab }
					tabs={ [
						{ name: 'certificate', title: __( 'Certificate', 'events-certificates' ) },
						{ name: 'people', title: __( 'Participants', 'events-certificates' ) },
					] }
				>
					{ ( tab ) => (
						<div className={ 'evcert-generator__tab-body' + ( 'people' === tab.name ? '' : ' is-flush' ) }>
							{ 'people' === tab.name ? (
								<VStack spacing={ 4 }>
					<CsvField file={ file } busy={ working } onFile={ ( csv ) => addPeople( '', csv ) } />
					<div className="evcert-people">
						<div className="evcert-people__bar">
							<span className="evcert-people__count">{ sprintf( __( '%1$d of %2$d selected', 'events-certificates' ), selected, people.length ) }</span>
							<Button variant="link" disabled={ ! people.length } onClick={ () => setPeople( setAll( people, selected !== people.length ) ) }>
								{ selected === people.length && people.length ? __( 'Deselect all', 'events-certificates' ) : __( 'Select all', 'events-certificates' ) }
							</Button>
						</div>
						{ ! people.length && <p className="evcert-people__empty">{ __( 'No participants yet. Import the Meetup CSV or add names by hand.', 'events-certificates' ) }</p> }
						<ul className="evcert-people__list" hidden={ ! people.length }>
							{ people.map( ( person ) => (
								<li key={ person.id } className={ 'evcert-people__row' + ( person.id === current ? ' is-current' : '' ) }>
									<input
										type="checkbox"
										id={ 'evcert-person-on-' + person.id }
										name={ 'evcert_person_on_' + person.id }
										checked={ person.on }
										aria-label={ sprintf( __( 'Include %s', 'events-certificates' ), person.name ) }
										onChange={ ( e ) => update( person.id, { on: e.target.checked } ) }
									/>
									<TextControl
										{ ...field }
										className="evcert-people__name"
										name={ 'evcert_person_name_' + person.id }
										label={ __( 'Participant name', 'events-certificates' ) }
										hideLabelFromVision
										value={ person.name }
										onFocus={ () => setCurrent( person.id ) }
										onChange={ ( name: string ) => update( person.id, { name } ) }
									/>
									{ person.roleLabels.map( ( label ) => (
										<span key={ label } className="evcert-people__tag">{ label }</span>
									) ) }
									{ 'no_show' === person.status && <span className="evcert-people__tag">{ __( 'did not attend', 'events-certificates' ) }</span> }
									<Button
										variant="tertiary"
										size="small"
										disabled={ working || missingOne.length > 0 }
										aria-label={ sprintf( __( 'Download the certificate of %s', 'events-certificates' ), person.name ) }
										onClick={ () => {
											setCurrent( person.id );
											submit( 'pdf', [ person ], true );
										} }
									>
										PDF
									</Button>
								</li>
							) ) }
						</ul>
					</div>
					<div className="evcert-people__add">
						<Button variant="link" aria-expanded={ adding } onClick={ () => setAdding( ! adding ) }>
							{ __( 'Add names', 'events-certificates' ) }
						</Button>
						{ adding && (
							<VStack spacing={ 3 }>
								<TextareaControl
									{ ...field }
									label={ __( 'Or add names by hand, one per line', 'events-certificates' ) }
									value={ values.lines }
									rows={ 4 }
									help={ __( 'One per line: name, and optionally e-mail and role, separated by commas (or pasted from a spreadsheet).', 'events-certificates' ) }
									onChange={ set( 'lines' ) }
								/>
								<Button variant="secondary" disabled={ working || '' === values.lines.trim() } onClick={ () => addPeople( values.lines ) }>
									{ __( 'Add names', 'events-certificates' ) }
								</Button>
							</VStack>
						) }
					</div>
					<p className="evcert-generator__need" id="evcert-generator-need" role="status">
						{ working ? __( 'Generating PDF…', 'events-certificates' ) : need || needOne || note }
					</p>
				</VStack>
							) : (
								<Panel>
									<div className="evcert-generator__event">
										<div className="evcert-summary">
					<div className="evcert-summary__title">
						{ /* The title, once set, is the control that opens Rename; before it, a link asks for it. */ }
						{ values.title ? (
							<Button variant="tertiary" className="evcert-summary__title-text" onClick={ () => setRenaming( true ) } label={ __( 'Rename' ) } showTooltip>
								{ values.title }
							</Button>
						) : (
							<Button variant="link" onClick={ () => setRenaming( true ) }>
								{ __( 'Event name…', 'events-certificates' ) }
							</Button>
						) }
					</div>
					<ImageRow image={ values.logo } vector={ values.logo_svg } label={ __( 'Set community logo', 'events-certificates' ) } title={ __( 'Community logo', 'events-certificates' ) } onChange={ ( image, svg ) => setValues( ( old ) => ( { ...old, logo: image, logo_svg: svg } ) ) } />
					{ row( 'community', __( 'Community', 'events-certificates' ), { placeholder: examples.community ?? '' } ) }
					<SummaryRow
						label={ __( 'Start', 'events-certificates' ) }
						value={ startText( values.date, values.start_time ) }
						placeholder={ __( 'Pick the date and time', 'events-certificates' ) }
						action={ { label: __( 'Now', 'events-certificates' ), onClick: () => { const now = new Date(); setValues( ( old ) => ( { ...old, date: now.getFullYear() + '-' + pad( now.getMonth() + 1 ) + '-' + pad( now.getDate() ), start_time: pad( now.getHours() ) + ':' + pad( now.getMinutes() ) } ) ); } } }
					>
						<DateTimePicker
							is12Hour={ is12HourTime }
							startOfWeek={ getDateSettings().l10n.startOfWeek }
							currentDate={ values.date ? values.date + 'T' + ( values.start_time || '00:00' ) + ':00' : null }
							onChange={ ( picked: string | null ) => {
								if ( ! picked ) {
									return;
								}
								const when = new Date( picked );
								setValues( ( old ) => ( {
									...old,
									date: when.getFullYear() + '-' + pad( when.getMonth() + 1 ) + '-' + pad( when.getDate() ),
									start_time: pad( when.getHours() ) + ':' + pad( when.getMinutes() ),
								} ) );
							} }
						/>
					</SummaryRow>
					<SummaryRow
						label={ __( 'End', 'events-certificates' ) }
						value={ 'custom' === endMode ? startText( values.date, endTime ) : ( LENGTHS.find( ( [ minutes ] ) => minutes === endMode )?.[ 1 ] ?? '' ) + ( endTime ? ' (' + endTime + ')' : '' ) }
						placeholder={ __( 'Set an end time…', 'events-certificates' ) }
						action={ 'custom' === endMode ? { label: __( 'Length', 'events-certificates' ), onClick: () => setEndMode( 120 ) } : undefined }
					>
						{ ( close ) =>
							'custom' === endMode ? (
								// A certificate is one day long, so only the time of the end is kept: the other days cannot be picked.
								<DateTimePicker
									is12Hour={ is12HourTime }
									startOfWeek={ getDateSettings().l10n.startOfWeek }
									currentDate={ ( values.date || todayLocal() ) + 'T' + ( values.end_time || values.start_time || '00:00' ) + ':00' }
									isInvalidDate={ ( day: Date ) => !! values.date && day.getFullYear() + '-' + pad( day.getMonth() + 1 ) + '-' + pad( day.getDate() ) !== values.date }
									onChange={ ( picked: string | null ) => {
										if ( picked ) {
											const when = new Date( picked );
											set( 'end_time' )( pad( when.getHours() ) + ':' + pad( when.getMinutes() ) );
										}
									} }
								/>
							) : (
								<MenuGroup label={ __( 'Length', 'events-certificates' ) }>
									{ LENGTHS.map( ( [ minutes, label ] ) => (
										<MenuItem
											key={ minutes }
											role="menuitemradio"
											isSelected={ endMode === minutes }
											onClick={ () => {
												setEndMode( minutes );
												close();
											} }
										>
											{ label }
										</MenuItem>
									) ) }
									<MenuItem
										role="menuitemradio"
										isSelected={ false }
										disabled={ ! values.date }
										onClick={ () => {
											setValues( ( old ) => ( { ...old, end_time: endTime || old.start_time } ) );
											setEndMode( 'custom' );
										} }
									>
										{ __( 'Set an end time…', 'events-certificates' ) }
									</MenuItem>
								</MenuGroup>
							)
						}
					</SummaryRow>
					{ row( 'venue', __( 'Venue', 'events-certificates' ), { placeholder: examples.venue ?? '' } ) }
					{ row( 'city', __( 'City', 'events-certificates' ), { placeholder: examples.city ?? '' } ) }
					{ row( 'speakers', __( 'Speaker(s)', 'events-certificates' ), { placeholder: examples.speakers ?? '', help: __( 'optional', 'events-certificates' ) } ) }
					{ row( 'link', __( 'Event link', 'events-certificates' ), { type: 'url', placeholder: 'https://', help: __( 'optional, goes in the footer', 'events-certificates' ) } ) }
					<ToggleControl { ...field } label={ __( 'Show the workload on the certificate', 'events-certificates' ) } checked={ showHours } onChange={ setShowHours } />
				</div>
									</div>
									<PanelBody title={ __( 'Signature', 'events-certificates' ) } initialOpen={ false }>
										<ImageRow global kind="signature" image={ values.signature_image } vector={ values.signature_svg } label={ __( 'Set signature image', 'events-certificates' ) } title={ __( 'Signature image', 'events-certificates' ) } onChange={ ( image, svg ) => setValues( ( old ) => ( { ...old, signature_image: image, signature_svg: svg } ) ) } />
										<div className="evcert-summary">
					{ row( 'signer_name', __( 'Name of who signs', 'events-certificates' ), { placeholder: config.signerName || __( 'Who signs the certificate', 'events-certificates' ) } ) }
					{ row( 'signer_role', _x( 'Role', 'role of who signs, in the generator', 'events-certificates' ), {
						placeholder: config.signerRole || _x( 'Organizer', 'default role of who signs, in the generator', 'events-certificates' ),
						help: __( 'Leave empty to use who signs in Settings.', 'events-certificates' ),
					} ) }
				</div>
									</PanelBody>
								</Panel>
							) }
						</div>
					) }
				</TabPanel>
			<Button className="evcert-generator__sidebar-close" icon={ closeSmall } label={ __( 'Close' ) } onClick={ () => setOpen( false ) } />
			{ renaming && <RenameModal title={ values.title } onSave={ ( title ) => { set( 'title' )( title ); setRenaming( false ); } } onClose={ () => setRenaming( false ) } /> }
			{ actionsRoot &&
				createPortal(
					<>
						<Button variant="tertiary" disabled={ working || missingOne.length > 0 } title={ needOne || __( 'Download just this one', 'events-certificates' ) } aria-describedby="evcert-generator-need" onClick={ () => currentPerson && submit( 'pdf', [ currentPerson ], true ) }>
							{ __( 'Download current', 'events-certificates' ) }
						</Button>
						<Button variant="secondary" disabled={ working || missingAll.length > 0 } title={ need || __( 'Download single PDF', 'events-certificates' ) } aria-describedby="evcert-generator-need" onClick={ () => submit( 'pdf', selectedPeople( people ), false ) }>
							{ __( 'Download PDF', 'events-certificates' ) }
						</Button>
						<Button variant="primary" disabled={ working || missingAll.length > 0 } title={ need || __( 'Download ZIP (one PDF each)', 'events-certificates' ) } aria-describedby="evcert-generator-need" onClick={ () => submit( 'zip', selectedPeople( people ), false ) }>
							{ __( 'Download ZIP', 'events-certificates' ) }
						</Button>
					</>,
					actionsRoot
				) }
			{ Object.keys( values ).filter( ( key ) => 'lines' !== key ).map( ( key ) => <Hidden key={ key } name={ 'evcert_' + key } value={ 'end_time' === key ? endTime : values[ key ] } /> ) }
			<Hidden name="evcert_show_hours" value={ showHours ? '1' : '0' } />
			{ posting && <Hidden name="evcert_generator_format" value={ posting.format } /> }
			{ posting?.rows.map( ( person, i ) => (
				<span key={ person.id }>
					<Hidden name={ 'evcert_people[' + i + '][name]' } value={ person.name } />
					<Hidden name={ 'evcert_people[' + i + '][email]' } value={ person.email } />
					{ person.roles.map( ( role ) => (
						<Hidden key={ role } name={ 'evcert_people[' + i + '][roles][]' } value={ role } />
					) ) }
				</span>
			) ) }
		</Panel>
		</OpenRow.Provider>
	);
}

// The tutorial drawer: the "?" button of the header and the commands open it, its own button closes it.
const help = document.getElementById( 'evcert-generator-help' );
const left = document.getElementById( 'evcert-generator-left-panel' );
const setHelp = ( open: boolean ) => {
	left?.classList.toggle( 'is-open', open );
	left?.setAttribute( 'aria-hidden', open ? 'false' : 'true' );
	help?.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	window.dispatchEvent( new CustomEvent( 'evcert:help-changed', { detail: { open } } ) );
};
help?.addEventListener( 'click', () => setHelp( ! left?.classList.contains( 'is-open' ) ) );
window.addEventListener( 'evcert:open-help', () => setHelp( true ) );
window.addEventListener( 'evcert:close-help', () => setHelp( false ) );

// The bar at the top, as the editor's document bar, opens the command palette.
const docbar = document.getElementById( 'evcert-generator-docbar' );
docbar?.addEventListener( 'click', () => ( dispatch( commandsStore ) as unknown as { open: () => void } ).open() );

const leftRoot = document.getElementById( 'evcert-generator-left-root' );
if ( leftRoot && config.tutorial ) {
	createRoot( leftRoot ).render( <TutorialPanel tutorial={ config.tutorial } /> );
}
const root = document.getElementById( 'evcert-generator-root' );
if ( root ) {
	createRoot( root ).render( <Form /> );
}
