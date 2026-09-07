<?php
/**
 * FPDF v1.86 - Motor PDF Ultra-Ligero de Producción (Zero-Bloat)
 */

if ( ! class_exists( 'FPDF' ) ) {
	define( 'FPDF_VERSION', '1.86' );

	class FPDF {
		protected $page;
		protected $n;
		protected $offsets;
		protected $buffer;
		protected $pages;
		protected $state;
		protected $compress;
		protected $k;
		protected $DefOrientation;
		protected $CurOrientation;
		protected $StdPageSizes;
		protected $DefPageSize;
		protected $CurPageSize;
		protected $CurRotation;
		protected $PageInfo;
		protected $wPt, $hPt;
		protected $w, $h;
		protected $lMargin;
		protected $tMargin;
		protected $rMargin;
		protected $bMargin;
		protected $cMargin;
		protected $x, $y;
		protected $lasth;
		protected $LineWidth;
		protected $fontpath;
		protected $CoreFonts;
		protected $fonts;
		protected $FontFiles;
		protected $encodings;
		protected $cmaps;
		protected $FontFamily;
		protected $FontStyle;
		protected $underline;
		protected $CurrentFont;
		protected $FontSizePt;
		protected $FontSize;
		protected $DrawColor;
		protected $FillColor;
		protected $TextColor;
		protected $ColorFlag;
		protected $WithAlpha;
		protected $ws;
		protected $images;
		protected $PageLinks;
		protected $links;
		protected $AutoPageBreak;
		protected $PageBreakTrigger;
		protected $InHeader;
		protected $InFooter;
		protected $AliasNbPages;
		protected $ZoomMode;
		protected $LayoutMode;
		protected $metadata;
		protected $pdf_version;

		public function __construct( $orientation = 'P', $unit = 'mm', $size = 'A4' ) {
			$this->_doinit( $orientation, $unit, $size );
		}

		protected function _doinit( $orientation, $unit, $size ) {
			$this->state     = 0;
			$this->page      = 0;
			$this->n         = 2;
			$this->buffer    = '';
			$this->pages     = array();
			$this->PageInfo  = array();
			$this->offsets   = array();
			$this->fonts     = array();
			$this->FontFiles = array();
			$this->encodings = array();
			$this->cmaps     = array();
			$this->images    = array();
			$this->links     = array();
			$this->PageLinks = array();
			$this->offsets   = array();
			$this->compress  = function_exists( 'gzcompress' );

			$this->CoreFonts = array( 'courier', 'helvetica', 'times', 'zapfdingbats' );

			if ( 'pt' === $unit ) {
				$this->k = 1;
			} elseif ( 'mm' === $unit ) {
				$this->k = 72 / 25.4;
			} elseif ( 'cm' === $unit ) {
				$this->k = 72 / 2.54;
			} elseif ( 'in' === $unit ) {
				$this->k = 72;
			} else {
				$this->Error( 'Incorrect unit: ' . $unit );
			}

			$this->StdPageSizes = array(
				'a3' => array( 841.89, 1190.55 ),
				'a4' => array( 595.28, 841.89 ),
				'a5' => array( 419.53, 595.28 ),
				'letter' => array( 612, 792 ),
				'legal'  => array( 612, 1008 ),
			);

			$size = $this->_getpagesize( $size );
			$this->DefPageSize = $size;
			$this->CurPageSize = $size;

			$orientation = strtolower( $orientation );
			if ( 'p' === $orientation || 'portrait' === $orientation ) {
				$this->DefOrientation = 'P';
				$this->w = $size[0];
				$this->h = $size[1];
			} elseif ( 'l' === $orientation || 'landscape' === $orientation ) {
				$this->DefOrientation = 'L';
				$this->w = $size[1];
				$this->h = $size[0];
			} else {
				$this->Error( 'Incorrect orientation: ' . $orientation );
			}

			$this->CurOrientation = $this->DefOrientation;
			$this->wPt = $this->w * $this->k;
			$this->hPt = $this->h * $this->k;
			$this->CurRotation = 0;

			$margin = 28.35 / $this->k;
			$this->SetMargins( $margin, $margin );
			$this->cMargin = $margin / 10;
			$this->LineWidth = .567 / $this->k;
			$this->SetAutoPageBreak( true, 2 * $margin );
			$this->SetDisplayMode( 'default' );
			$this->SetLineWidth( $this->LineWidth );
			$this->pdf_version = '1.3';
		}

		public function SetMargins( $left, $top, $right = null ) {
			$this->lMargin = $left;
			$this->tMargin = $top;
			if ( null === $right ) {
				$right = $left;
			}
			$this->rMargin = $right;
		}

		public function SetLeftMargin( $margin ) { $this->lMargin = $margin; if ( $this->page > 0 && $this->x < $margin ) $this->x = $margin; }
		public function SetTopMargin( $margin ) { $this->tMargin = $margin; }
		public function SetRightMargin( $margin ) { $this->rMargin = $margin; }

		public function SetAutoPageBreak( $auto, $margin = 0 ) {
			$this->AutoPageBreak = $auto;
			$this->bMargin = $margin;
			$this->PageBreakTrigger = $this->h - $margin;
		}

		public function SetDisplayMode( $zoom, $layout = 'default' ) {
			$this->ZoomMode = $zoom;
			$this->LayoutMode = $layout;
		}

		public function SetLineWidth( $width ) {
			$this->LineWidth = $width;
			if ( $this->page > 0 ) $this->_out( sprintf( '%.2F w', $width * $this->k ) );
		}

		public function AddPage( $orientation = '', $size = '', $rotation = 0 ) {
			if ( 0 === $this->state ) $this->Open();
			$family = $this->FontFamily;
			$style = $this->FontStyle . ( $this->underline ? 'U' : '' );
			$fontsize = $this->FontSizePt;
			$lw = $this->LineWidth;
			$dc = $this->DrawColor;
			$fc = $this->FillColor;
			$tc = $this->TextColor;

			if ( $this->page > 0 ) {
				$this->InFooter = true;
				$this->Footer();
				$this->InFooter = false;
				$this->_endpage();
			}

			$this->_beginpage( $orientation, $size, $rotation );
			$this->_out( '2 J' );
			$this->LineWidth = $lw;
			$this->_out( sprintf( '%.2F w', $lw * $this->k ) );
			if ( $family ) $this->SetFont( $family, $style, $fontsize );
			$this->DrawColor = $dc;
			if ( '0 G' !== $dc ) $this->_out( $dc );
			$this->FillColor = $fc;
			if ( '0 g' !== $fc ) $this->_out( $fc );
			$this->TextColor = $tc;

			$this->InHeader = true;
			$this->Header();
			$this->InHeader = false;
		}

		public function Header() {}
		public function Footer() {}
		public function PageNo() { return $this->page; }

		public function SetDrawColor( $r, $g = null, $b = null ) {
			if ( ( 0 === $r && 0 === $g && 0 === $b ) || null === $g ) $this->DrawColor = sprintf( '%.3F G', $r / 255 );
			else $this->DrawColor = sprintf( '%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255 );
			if ( $this->page > 0 ) $this->_out( $this->DrawColor );
		}

		public function SetFillColor( $r, $g = null, $b = null ) {
			if ( ( 0 === $r && 0 === $g && 0 === $b ) || null === $g ) $this->FillColor = sprintf( '%.3F g', $r / 255 );
			else $this->FillColor = sprintf( '%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255 );
			if ( $this->page > 0 ) $this->_out( $this->FillColor );
		}

		public function SetTextColor( $r, $g = null, $b = null ) {
			if ( ( 0 === $r && 0 === $g && 0 === $b ) || null === $g ) $this->TextColor = sprintf( '%.3F g', $r / 255 );
			else $this->TextColor = sprintf( '%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255 );
		}

		public function GetStringWidth( $s ) {
			$s = (string) $s;
			$cw = &$this->CurrentFont['cw'];
			$w = 0;
			$l = strlen( $s );
			for ( $i = 0; $i < $l; $i++ ) $w += isset( $cw[ $s[ $i ] ] ) ? $cw[ $s[ $i ] ] : 600;
			return $w * $this->FontSize / 1000;
		}

		public function SetFont( $family, $style = '', $size = 0 ) {
			$family = strtolower( $family );
			if ( '' === $family ) $family = $this->FontFamily;
			if ( 'arial' === $family ) $family = 'helvetica';
			$style = strtoupper( $style );
			if ( strpos( $style, 'U' ) !== false ) { $this->underline = true; $style = str_replace( 'U', '', $style ); } else $this->underline = false;
			if ( 'B' === $style ) $style = 'B';
			if ( 0 === $size ) $size = $this->FontSizePt;

			$fontkey = $family . $style;
			if ( ! isset( $this->fonts[ $fontkey ] ) ) {
				if ( in_array( $family, $this->CoreFonts, true ) ) {
					$this->fonts[ $fontkey ] = array(
						'i' => count( $this->fonts ) + 1,
						'type' => 'core',
						'name' => 'Helvetica' . ( 'B' === $style ? '-Bold' : ( 'I' === $style ? '-Oblique' : '' ) ),
						'up' => -100,
						'ut' => 50,
						'cw' => array(),
					);
				} else {
					$family = 'helvetica';
					$fontkey = $family . $style;
					$this->fonts[ $fontkey ] = array(
						'i' => count( $this->fonts ) + 1,
						'type' => 'core',
						'name' => 'Helvetica',
						'up' => -100,
						'ut' => 50,
						'cw' => array(),
					);
				}
			}

			$this->FontFamily = $family;
			$this->FontStyle = $style;
			$this->FontSizePt = $size;
			$this->FontSize = $size / $this->k;
			$this->CurrentFont = &$this->fonts[ $fontkey ];
			if ( $this->page > 0 ) $this->_out( sprintf( 'BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt ) );
		}

		public function SetFontSize( $size ) {
			$this->FontSizePt = $size;
			$this->FontSize = $size / $this->k;
			if ( $this->page > 0 ) $this->_out( sprintf( 'BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt ) );
		}

		public function Rect( $x, $y, $w, $h, $style = '' ) {
			$op = 'D';
			if ( 'F' === $style ) $op = 'f';
			elseif ( 'FD' === $style || 'DF' === $style ) $op = 'B';
			$this->_out( sprintf( '%.2F %.2F %.2F %.2F re %s', $x * $this->k, ( $this->h - $y ) * $this->k, $w * $this->k, -$h * $this->k, $op ) );
		}

		public function Line( $x1, $y1, $x2, $y2 ) {
			$this->_out( sprintf( '%.2F %.2F m %.2F %.2F l S', $x1 * $this->k, ( $this->h - $y1 ) * $this->k, $x2 * $this->k, ( $this->h - $y2 ) * $this->k ) );
		}

		public function Cell( $w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '' ) {
			$k = $this->k;
			if ( $this->y + $h > $this->PageBreakTrigger && ! $this->InHeader && ! $this->InFooter && $this->AcceptPageBreak() ) {
				$x = $this->x;
				$ws = $this->ws;
				if ( $ws > 0 ) { $this->ws = 0; $this->_out( '0 Tw' ); }
				$this->AddPage( $this->CurOrientation, $this->CurPageSize, $this->CurRotation );
				$this->x = $x;
				if ( $ws > 0 ) { $this->ws = $ws; $this->_out( sprintf( '%.3F Tw', $ws * $k ) ); }
			}
			if ( 0 === $w ) $w = $this->w - $this->rMargin - $this->x;

			$s = '';
			if ( $fill || 1 === $border ) {
				$op = ( $fill ) ? ( ( 1 === $border ) ? 'B' : 'f' ) : 'S';
				$s = sprintf( '%.2F %.2F %.2F %.2F re %s ', $this->x * $k, ( $this->h - $this->y ) * $k, $w * $k, -$h * $k, $op );
			}
			if ( is_string( $border ) ) {
				$x = $this->x;
				$y = $this->y;
				if ( strpos( $border, 'L' ) !== false ) $s .= sprintf( '%.2F %.2F m %.2F %.2F l S ', $x * $k, ( $this->h - $y ) * $k, $x * $k, ( $this->h - ( $y + $h ) ) * $k );
				if ( strpos( $border, 'T' ) !== false ) $s .= sprintf( '%.2F %.2F m %.2F %.2F l S ', $x * $k, ( $this->h - $y ) * $k, ( $x + $w ) * $k, ( $this->h - $y ) * $k );
				if ( strpos( $border, 'R' ) !== false ) $s .= sprintf( '%.2F %.2F m %.2F %.2F l S ', ( $x + $w ) * $k, ( $this->h - $y ) * $k, ( $x + $w ) * $k, ( $this->h - ( $y + $h ) ) * $k );
				if ( strpos( $border, 'B' ) !== false ) $s .= sprintf( '%.2F %.2F m %.2F %.2F l S ', $x * $k, ( $this->h - ( $y + $h ) ) * $k, ( $x + $w ) * $k, ( $this->h - ( $y + $h ) ) * $k );
			}

			if ( '' !== $txt ) {
				$txt = str_replace( array( "\r", "\n" ), '', $txt );
				if ( 'R' === $align ) $dx = $w - $this->cMargin - $this->GetStringWidth( $txt );
				elseif ( 'C' === $align ) $dx = ( $w - $this->GetStringWidth( $txt ) ) / 2;
				else $dx = $this->cMargin;

				if ( $this->ColorFlag ) $s .= 'q ' . $this->TextColor . ' ';
				$s .= sprintf( 'BT %.2F %.2F Td (%s) Tj ET', ( $this->x + $dx ) * $k, ( $this->h - ( $this->y + .5 * $h + .3 * $this->FontSize ) ) * $k, $this->_escape( $txt ) );
				if ( $this->ColorFlag ) $s .= ' Q';
			}

			if ( $s ) $this->_out( $s );
			$this->lasth = $h;

			if ( $ln > 0 ) {
				$this->y += $h;
				if ( 1 === $ln ) $this->x = $this->lMargin;
			} else {
				$this->x += $w;
			}
		}

		public function MultiCell( $w, $h, $txt, $border = 0, $align = 'J', $fill = false ) {
			$cw = &$this->CurrentFont['cw'];
			if ( 0 === $w ) $w = $this->w - $this->rMargin - $this->x;
			$wmax = ( $w - 2 * $this->cMargin ) * 1000 / $this->FontSize;
			$s = str_replace( "\r", '', $txt );
			$nb = strlen( $s );
			if ( $nb > 0 && "\n" === $s[ $nb - 1 ] ) $nb--;

			$sep = -1;
			$i = 0;
			$j = 0;
			$l = 0;
			$ns = 0;
			$nl = 1;

			while ( $i < $nb ) {
				$c = $s[ $i ];
				if ( "\n" === $c ) {
					$this->Cell( $w, $h, substr( $s, $j, $i - $j ), $border, 2, $align, $fill );
					$i++;
					$sep = -1;
					$j = $i;
					$l = 0;
					$ns = 0;
					$nl++;
					continue;
				}
				if ( ' ' === $c ) { $sep = $i; $ns++; }
				$l += isset( $cw[ $c ] ) ? $cw[ $c ] : 600;

				if ( $l > $wmax ) {
					if ( -1 === $sep ) {
						if ( $i === $j ) $i++;
						$this->Cell( $w, $h, substr( $s, $j, $i - $j ), $border, 2, $align, $fill );
					} else {
						$this->Cell( $w, $h, substr( $s, $j, $sep - $j ), $border, 2, $align, $fill );
						$i = $sep + 1;
					}
					$sep = -1;
					$j = $i;
					$l = 0;
					$ns = 0;
					$nl++;
				} else {
					$i++;
				}
			}
			if ( $i !== $j ) $this->Cell( $w, $h, substr( $s, $j, $i - $j ), $border, 2, $align, $fill );
			$this->x = $this->lMargin;
		}

		public function GetX() { return $this->x; }
		public function SetX( $x ) { if ( $x >= 0 ) $this->x = $x; else $this->x = $this->w + $x; }
		public function GetY() { return $this->y; }
		public function SetY( $y, $resetX = true ) { if ( $y >= 0 ) $this->y = $y; else $this->y = $this->h + $y; if ( $resetX ) $this->x = $this->lMargin; }
		public function SetXY( $x, $y ) { $this->SetY( $y, false ); $this->SetX( $x ); }

		public function Output( $dest = '', $name = '', $isUTF8 = false ) {
			if ( 0 === $this->state ) $this->Open();
			if ( $this->page > 0 ) {
				$this->InFooter = true;
				$this->Footer();
				$this->InFooter = false;
				$this->_endpage();
			}

			$this->_enddoc();

			if ( '' === $dest ) {
				$dest = 'I';
			}
			if ( '' === $name ) {
				$name = 'doc.pdf';
			}

			switch ( strtoupper( $dest ) ) {
				case 'I':
					$this->_checkoutput();
					if ( PHP_SAPI !== 'cli' ) {
						header( 'Content-Type: application/pdf' );
						header( 'Content-Disposition: inline; filename="' . $name . '"' );
						header( 'Cache-Control: private, max-age=0, must-revalidate' );
						header( 'Pragma: public' );
					}
					echo $this->buffer;
					break;
				case 'D':
					$this->_checkoutput();
					header( 'Content-Type: application/x-download' );
					header( 'Content-Disposition: attachment; filename="' . $name . '"' );
					header( 'Cache-Control: private, max-age=0, must-revalidate' );
					header( 'Pragma: public' );
					echo $this->buffer;
					break;
				case 'F':
					file_put_contents( $name, $this->buffer );
					break;
				case 'S':
					return $this->buffer;
			}
			return '';
		}

		protected function Open() { $this->state = 1; }
		protected function AcceptPageBreak() { return $this->AutoPageBreak; }

		protected function _checkoutput() {
			if ( PHP_SAPI !== 'cli' && headers_sent( $file, $line ) ) {
				$this->Error( "Some data has already been output, can't send PDF file (output started at $file:$line)" );
			}
		}

		protected function _getpagesize( $size ) {
			if ( is_string( $size ) ) {
				$size = strtolower( $size );
				if ( ! isset( $this->StdPageSizes[ $size ] ) ) $this->Error( 'Unknown page size: ' . $size );
				$a = $this->StdPageSizes[ $size ];
				return array( $a[0] / $this->k, $a[1] / $this->k );
			}
			if ( $size[0] > $size[1] ) return array( $size[1], $size[0] );
			return $size;
		}

		protected function _beginpage( $orientation, $size, $rotation ) {
			$this->page++;
			$this->pages[ $this->page ] = '';
			$this->state = 2;
			$this->x = $this->lMargin;
			$this->y = $this->tMargin;
			$this->FontFamily = '';

			if ( '' === $orientation ) $orientation = $this->DefOrientation;
			else $orientation = strtoupper( $orientation[0] );

			if ( '' === $size ) $size = $this->DefPageSize;
			else $size = $this->_getpagesize( $size );

			if ( $orientation !== $this->CurOrientation || $size[0] !== $this->CurPageSize[0] || $size[1] !== $this->CurPageSize[1] ) {
				if ( 'P' === $orientation ) {
					$this->w = $size[0];
					$this->h = $size[1];
				} else {
					$this->w = $size[1];
					$this->h = $size[0];
				}
				$this->wPt = $this->w * $this->k;
				$this->hPt = $this->h * $this->k;
				$this->PageBreakTrigger = $this->h - $this->bMargin;
				$this->CurOrientation = $orientation;
				$this->CurPageSize = $size;
			}
		}

		protected function _endpage() { $this->state = 1; }

		protected function _escape( $s ) {
			$s = str_replace( '\\', '\\\\', $s );
			$s = str_replace( '(', '\\(', $s );
			$s = str_replace( ')', '\\)', $s );
			return str_replace( "\r", '\\r', $s );
		}

		protected function _out( $s ) {
			if ( 2 === $this->state ) $this->pages[ $this->page ] .= $s . "\n";
			else $this->buffer .= $s . "\n";
		}

		protected function _enddoc() {
			$this->_putheader();
			$this->_putpages();
			$this->_putresources();
			$this->_putinfo();
			$this->_putcatalog();

			$offset = strlen( $this->buffer );
			$this->_out( 'xref' );
			$this->_out( '0 ' . ( $this->n + 1 ) );
			$this->_out( '0000000000 65535 f ' );
			for ( $i = 1; $i <= $this->n; $i++ ) $this->_out( sprintf( '%010d 00000 n ', $this->offsets[ $i ] ) );

			$this->_out( 'trailer' );
			$this->_out( '<<' );
			$this->_out( '/Size ' . ( $this->n + 1 ) );
			$this->_out( '/Root ' . $this->n . ' 0 R' );
			$this->_out( '/Info ' . ( $this->n - 1 ) . ' 0 R' );
			$this->_out( '>>' );
			$this->_out( 'startxref' );
			$this->_out( $offset );
			$this->_out( '%%EOF' );
			$this->state = 3;
		}

		protected function _putheader() {
			$this->_out( '%PDF-' . $this->pdf_version );
		}

		protected function _putpages() {
			$nb = $this->page;
			for ( $n = 1; $n <= $nb; $n++ ) {
				$this->_newobj();
				$this->_out( '<</Type /Page' );
				$this->_out( '/Parent 1 0 R' );
				$this->_out( sprintf( '/MediaBox [0 0 %.2F %.2F]', $this->CurPageSize[0] * $this->k, $this->CurPageSize[1] * $this->k ) );
				$this->_out( '/Resources 2 0 R' );
				$this->_out( '/Contents ' . ( $this->n + 1 ) . ' 0 R>>' );
				$this->_out( 'endobj' );

				$p = $this->pages[ $n ];
				if ( $this->compress ) {
					$p = gzcompress( $p );
					$opt = '/Filter /FlateDecode ';
				} else {
					$opt = '';
				}
				$this->_newobj();
				$this->_out( '<<' . $opt . '/Length ' . strlen( $p ) . '>>' );
				$this->_putstream( $p );
				$this->_out( 'endobj' );
			}

			$this->offsets[1] = strlen( $this->buffer );
			$this->_out( '1 0 R' );
			$this->_out( '<</Type /Pages' );
			$kids = '/Kids [';
			for ( $i = 0; $i < $nb; $i++ ) $kids .= ( 3 + 2 * $i ) . ' 0 R ';
			$this->_out( $kids . ']' );
			$this->_out( '/Count ' . $nb );
			$this->_out( '>>' );
			$this->_out( 'endobj' );
		}

		protected function _putresources() {
			$this->_putfonts();
			$this->offsets[2] = strlen( $this->buffer );
			$this->_out( '2 0 R' );
			$this->_out( '<<' );
			$this->_out( '/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]' );
			$this->_out( '/Font <<' );
			foreach ( $this->fonts as $font ) $this->_out( '/F' . $font['i'] . ' ' . $font['n'] . ' 0 R' );
			$this->_out( '>>' );
			$this->_out( '>>' );
			$this->_out( 'endobj' );
		}

		protected function _putfonts() {
			foreach ( $this->fonts as $k => &$font ) {
				$this->_newobj();
				$font['n'] = $this->n;
				$this->_out( '<</Type /Font' );
				$this->_out( '/Subtype /Type1' );
				$this->_out( '/BaseFont /' . $font['name'] );
				$this->_out( '/Encoding /WinAnsiEncoding' );
				$this->_out( '>>' );
				$this->_out( 'endobj' );
			}
		}

		protected function _putinfo() {
			$this->_newobj();
			$this->_out( '<<' );
			$this->_out( '/Producer (WP Agency Toolkit - FPDF ' . FPDF_VERSION . ')' );
			$this->_out( '/CreationDate (D:' . date( 'YmdHis' ) . ')' );
			$this->_out( '>>' );
			$this->_out( 'endobj' );
		}

		protected function _putcatalog() {
			$this->_newobj();
			$this->_out( '<</Type /Catalog' );
			$this->_out( '/Pages 1 0 R' );
			$this->_out( '>>' );
			$this->_out( 'endobj' );
		}

		protected function _newobj() {
			$this->n++;
			$this->offsets[ $this->n ] = strlen( $this->buffer );
			$this->_out( $this->n . ' 0 obj' );
		}

		protected function _putstream( $s ) {
			$this->_out( 'stream' );
			$this->_out( $s );
			$this->_out( 'endstream' );
		}

		protected function Error( $msg ) {
			throw new Exception( 'FPDF error: ' . $msg );
		}
	}
}
