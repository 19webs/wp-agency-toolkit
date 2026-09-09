<?php
/**
 * Módulo: Facturas PDF y Albaranes Automáticos (WooCommerce) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_PDF_Invoices {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_PDF_Invoices
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_PDF_Invoices
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Adjuntar PDF de Factura a correos de WooCommerce
		add_filter( 'woocommerce_email_attachments', array( $this, 'attach_pdf_to_email' ), 10, 3 );

		// Asignar número de factura al cambiar a Procesando o Completado
		add_action( 'woocommerce_order_status_completed', array( $this, 'auto_generate_invoice_number' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'auto_generate_invoice_number' ) );

		// Botones de descarga en la administración del pedido (WP Admin)
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'add_admin_order_download_buttons' ) );

		// Botón de descarga en la sección Mi Cuenta -> Pedidos del cliente
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_my_account_download_button' ), 10, 2 );

		// Endpoint de descarga de facturas/albaranes
		add_action( 'admin_init', array( $this, 'handle_pdf_download_request' ) );
		add_action( 'init', array( $this, 'handle_pdf_download_request' ) );
	}

	/**
	 * Asigna un número de factura correlativo si no tiene uno asignado.
	 *
	 * @param int $order_id ID del pedido.
	 */
	public function auto_generate_invoice_number( $order_id ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-pdf-invoices'] ) && empty( $settings['pdf_invoices_enabled'] ) ) {
			return;
		}

		$existing_num = get_post_meta( $order_id, '_wpat_invoice_number', true );
		if ( ! empty( $existing_num ) ) {
			return;
		}

		$prefix = isset( $settings['pdf_invoice_prefix'] ) ? sanitize_text_field( $settings['pdf_invoice_prefix'] ) : 'FACT-' . date( 'Y' ) . '-';
		$next_num = isset( $settings['pdf_invoice_next_num'] ) ? intval( $settings['pdf_invoice_next_num'] ) : 1;

		$formatted_num = $prefix . str_pad( $next_num, 4, '0', STR_PAD_LEFT );

		update_post_meta( $order_id, '_wpat_invoice_number', $formatted_num );
		update_post_meta( $order_id, '_wpat_invoice_date', date( 'Y-m-d H:i:s' ) );

		// Generar token de seguridad para descarga
		if ( ! get_post_meta( $order_id, '_wpat_invoice_token', true ) ) {
			update_post_meta( $order_id, '_wpat_invoice_token', wp_generate_password( 20, false ) );
		}

		// Incrementar siguiente número de factura en ajustes
		$settings['pdf_invoice_next_num'] = $next_num + 1;
		update_option( 'wpat_settings', $settings );
	}

	/**
	 * Adjunta el PDF de la factura al correo electrónico de confirmación del cliente.
	 */
	public function attach_pdf_to_email( $attachments, $email_id, $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return $attachments;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-pdf-invoices'] ) && empty( $settings['pdf_invoices_enabled'] ) ) {
			return $attachments;
		}

		$allowed_emails = array( 'customer_completed_order', 'customer_processing_order' );
		if ( ! in_array( $email_id, $allowed_emails, true ) ) {
			return $attachments;
		}

		$this->auto_generate_invoice_number( $order->get_id() );
		$pdf_path = $this->generate_pdf_file( $order->get_id(), 'invoice' );

		if ( $pdf_path && file_exists( $pdf_path ) ) {
			$attachments[] = $pdf_path;
		}

		return $attachments;
	}

	/**
	 * Muestra los botones de descarga de Factura y Albarán en el panel del pedido en WP Admin.
	 *
	 * @param WC_Order $order Objeto pedido.
	 */
	public function add_admin_order_download_buttons( $order ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-pdf-invoices'] ) && empty( $settings['pdf_invoices_enabled'] ) ) {
			return;
		}

		$order_id = $order->get_id();
		$this->auto_generate_invoice_number( $order_id );

		$inv_num = get_post_meta( $order_id, '_wpat_invoice_number', true );
		$token   = get_post_meta( $order_id, '_wpat_invoice_token', true );

		$inv_url  = add_query_arg( array( 'wpat_pdf_action' => 'download', 'type' => 'invoice', 'order_id' => $order_id, 'token' => $token ), home_url( '/' ) );
		$pack_url = add_query_arg( array( 'wpat_pdf_action' => 'download', 'type' => 'packing_slip', 'order_id' => $order_id, 'token' => $token ), home_url( '/' ) );

		echo '<div style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed #ccc;">';
		if ( ! empty( $inv_num ) ) {
			echo '<p><strong>' . esc_html__( 'Nº Factura:', 'wp-agency-toolkit' ) . '</strong> ' . esc_html( $inv_num ) . '</p>';
		}
		echo '<div style="display:flex; gap:8px;">';
		echo '<a href="' . esc_url( $inv_url ) . '" target="_blank" class="button button-secondary" style="font-size:12px;">📄 Descargar Factura PDF</a>';
		echo '<a href="' . esc_url( $pack_url ) . '" target="_blank" class="button button-secondary" style="font-size:12px;">📦 Descargar Albarán PDF</a>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Añade el botón de Descargar Factura en Mi Cuenta -> Pedidos para el cliente.
	 */
	public function add_my_account_download_button( $actions, $order ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-pdf-invoices'] ) && empty( $settings['pdf_invoices_enabled'] ) ) {
			return $actions;
		}

		$order_id = $order->get_id();
		$token   = get_post_meta( $order_id, '_wpat_invoice_token', true );

		if ( ! empty( $token ) ) {
			$inv_url = add_query_arg( array( 'wpat_pdf_action' => 'download', 'type' => 'invoice', 'order_id' => $order_id, 'token' => $token ), home_url( '/' ) );
			$actions['wpat_invoice'] = array(
				'url'  => $inv_url,
				'name' => __( 'Factura PDF', 'wp-agency-toolkit' ),
			);
		}

		return $actions;
	}

	/**
	 * Maneja la descarga directa del PDF mediante URL segura tokenizada.
	 */
	public function handle_pdf_download_request() {
		if ( empty( $_GET['wpat_pdf_action'] ) || 'download' !== $_GET['wpat_pdf_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type     = isset( $_GET['type'] ) && 'packing_slip' === $_GET['type'] ? 'packing_slip' : 'invoice'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token    = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $order_id || empty( $token ) ) {
			wp_die( esc_html__( 'Acceso denegado. Parámetros no válidos.', 'wp-agency-toolkit' ) );
		}

		$saved_token = get_post_meta( $order_id, '_wpat_invoice_token', true );
		if ( ! current_user_can( 'manage_options' ) && ( empty( $saved_token ) || $token !== $saved_token ) ) {
			wp_die( esc_html__( 'Acceso denegado. Token de seguridad no válido.', 'wp-agency-toolkit' ) );
		}

		$this->auto_generate_invoice_number( $order_id );
		$pdf_path = $this->generate_pdf_file( $order_id, $type );

		if ( file_exists( $pdf_path ) ) {
			$filename = ( 'packing_slip' === $type ? 'Albaran-' : 'Factura-' ) . $order_id . '.pdf';
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: inline; filename="' . $filename . '"' );
			header( 'Content-Length: ' . filesize( $pdf_path ) );
			readfile( $pdf_path );
			exit;
		} else {
			wp_die( esc_html__( 'Error al generar el archivo PDF.', 'wp-agency-toolkit' ) );
		}
	}

	/**
	 * Genera físicamente el archivo PDF en el servidor con FPDF y devuelve su ruta.
	 *
	 * @param int    $order_id ID del pedido.
	 * @param string $type     'invoice' o 'packing_slip'.
	 * @return string Ruta del archivo PDF.
	 */
	public function generate_pdf_file( $order_id, $type = 'invoice' ) {
		require_once WPAT_PATH . 'includes/libraries/fpdf/fpdf.php';

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return '';
		}

		$upload_dir = wp_upload_dir();
		$pdf_dir = $upload_dir['basedir'] . '/wpat-invoices/';
		if ( ! file_exists( $pdf_dir ) ) {
			wp_mkdir_p( $pdf_dir );
			file_put_contents( $pdf_dir . '.htaccess', "deny from all\n" );
		}

		$filename = ( 'packing_slip' === $type ? 'packing_slip_' : 'invoice_' ) . $order_id . '.pdf';
		$filepath = $pdf_dir . $filename;

		$settings   = WPAT_Main::get_instance()->get_settings();
		$comp_name  = ! empty( $settings['pdf_company_name'] ) ? $settings['pdf_company_name'] : get_bloginfo( 'name' );
		$comp_nif   = ! empty( $settings['pdf_company_nif'] ) ? $settings['pdf_company_nif'] : '';
		$comp_addr  = ! empty( $settings['pdf_company_address'] ) ? $settings['pdf_company_address'] : '';
		$comp_footer= ! empty( $settings['pdf_company_footer'] ) ? $settings['pdf_company_footer'] : '';

		$inv_num  = get_post_meta( $order_id, '_wpat_invoice_number', true );
		if ( empty( $inv_num ) ) {
			$inv_num = 'FACT-' . $order_id;
		}
		$inv_date = date_i18n( 'd/m/Y', strtotime( $order->get_date_created() ) );

		$pdf = new FPDF( 'P', 'mm', 'A4' );
		$pdf->AddPage();
		$pdf->SetFont( 'Helvetica', 'B', 16 );

		// Encabezado
		$pdf->SetTextColor( 15, 23, 42 );
		$title_doc = 'packing_slip' === $type ? 'ALBARÁN DE ENTREGA' : 'FACTURA DE VENTA';
		$pdf->Cell( 110, 8, utf8_decode( strtoupper( $comp_name ) ), 0, 0, 'L' );
		$pdf->SetFont( 'Helvetica', 'B', 14 );
		$pdf->SetTextColor( 37, 99, 235 );
		$pdf->Cell( 80, 8, utf8_decode( $title_doc ), 0, 1, 'R' );

		// Datos de empresa
		$pdf->SetFont( 'Helvetica', '', 9 );
		$pdf->SetTextColor( 71, 85, 105 );
		$pdf->Cell( 110, 4, utf8_decode( 'CIF/NIF: ' . $comp_nif ), 0, 0, 'L' );
		$pdf->Cell( 80, 4, utf8_decode( ( 'packing_slip' === $type ? 'Nº Pedido: ' : 'Nº Factura: ' ) . ( 'packing_slip' === $type ? '#' . $order_id : $inv_num ) ), 0, 1, 'R' );

		$pdf->Cell( 110, 4, utf8_decode( $comp_addr ), 0, 0, 'L' );
		$pdf->Cell( 80, 4, utf8_decode( 'Fecha: ' . $inv_date ), 0, 1, 'R' );
		$pdf->Ln( 8 );

		// Bloque Datos Cliente
		$pdf->SetFillColor( 248, 250, 252 );
		$pdf->SetFont( 'Helvetica', 'B', 10 );
		$pdf->SetTextColor( 15, 23, 42 );
		$pdf->Cell( 92, 6, utf8_decode( 'DATOS DE FACTURACIÓN' ), 1, 0, 'L', true );
		$pdf->Cell( 6, 6, '', 0, 0 );
		$pdf->Cell( 92, 6, utf8_decode( 'DATOS DE ENVÍO' ), 1, 1, 'L', true );

		$pdf->SetFont( 'Helvetica', '', 9 );
		$pdf->SetTextColor( 51, 65, 85 );

		$b_name  = $order->get_formatted_billing_full_name();
		$b_addr  = $order->get_billing_address_1() . ' ' . $order->get_billing_address_2();
		$b_city  = $order->get_billing_city() . ', ' . $order->get_billing_postcode();
		$b_nif   = get_post_meta( $order_id, '_billing_nif', true );

		$s_name  = $order->get_formatted_shipping_full_name();
		$s_addr  = $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2();
		$s_city  = $order->get_shipping_city() . ', ' . $order->get_shipping_postcode();

		$pdf->Cell( 92, 5, utf8_decode( $b_name ), 'L-R', 0 );
		$pdf->Cell( 6, 5, '', 0, 0 );
		$pdf->Cell( 92, 5, utf8_decode( $s_name ? $s_name : $b_name ), 'L-R', 1 );

		if ( ! empty( $b_nif ) ) {
			$pdf->Cell( 92, 5, utf8_decode( 'NIF/CIF: ' . $b_nif ), 'L-R', 0 );
			$pdf->Cell( 6, 5, '', 0, 0 );
			$pdf->Cell( 92, 5, utf8_decode( '' ), 'L-R', 1 );
		}

		$pdf->Cell( 92, 5, utf8_decode( $b_addr ), 'L-R', 0 );
		$pdf->Cell( 6, 5, '', 0, 0 );
		$pdf->Cell( 92, 5, utf8_decode( $s_addr ? $s_addr : $b_addr ), 'L-R', 1 );

		$pdf->Cell( 92, 5, utf8_decode( $b_city ), 'L-R-B', 0 );
		$pdf->Cell( 6, 5, '', 0, 0 );
		$pdf->Cell( 92, 5, utf8_decode( $s_city ? $s_city : $b_city ), 'L-R-B', 1 );
		$pdf->Ln( 8 );

		// Tabla de Productos
		$pdf->SetFillColor( 37, 99, 235 );
		$pdf->SetTextColor( 255, 255, 255 );
		$pdf->SetFont( 'Helvetica', 'B', 9 );

		if ( 'packing_slip' === $type ) {
			$pdf->Cell( 150, 7, utf8_decode( 'PRODUCTO / DESCRIPCIÓN' ), 1, 0, 'L', true );
			$pdf->Cell( 40, 7, utf8_decode( 'CANTIDAD' ), 1, 1, 'C', true );
		} else {
			$pdf->Cell( 100, 7, utf8_decode( 'PRODUCTO / DESCRIPCIÓN' ), 1, 0, 'L', true );
			$pdf->Cell( 25, 7, utf8_decode( 'CANT.' ), 1, 0, 'C', true );
			$pdf->Cell( 30, 7, utf8_decode( 'PRECIO U.' ), 1, 0, 'R', true );
			$pdf->Cell( 35, 7, utf8_decode( 'TOTAL' ), 1, 1, 'R', true );
		}

		$pdf->SetTextColor( 30, 41, 59 );
		$pdf->SetFont( 'Helvetica', '', 8.5 );

		foreach ( $order->get_items() as $item ) {
			$item_name = $item->get_name();

			// Incluir Opciones Extra de Producto desglosadas
			$meta_data = $item->get_formatted_meta_data( '_' );
			if ( ! empty( $meta_data ) ) {
				foreach ( $meta_data as $meta ) {
					$item_name .= "\n  " . wp_strip_all_tags( $meta->display_key . ': ' . $meta->display_value );
				}
			}

			$qty = $item->get_quantity();

			if ( 'packing_slip' === $type ) {
				$pdf->MultiCell( 150, 6, utf8_decode( $item_name ), 1, 'L' );
				$pdf->SetXY( 160, $pdf->GetY() - 6 );
				$pdf->Cell( 40, 6, $qty, 1, 1, 'C' );
			} else {
				$price = $order->get_item_subtotal( $item, false, true );
				$total = $item->get_total();

				$pdf->Cell( 100, 6, utf8_decode( mb_strimwidth( $item_name, 0, 55, '...' ) ), 1, 0, 'L' );
				$pdf->Cell( 25, 6, $qty, 1, 0, 'C' );
				$pdf->Cell( 30, 6, utf8_decode( number_format_i18n( $price, 2 ) . ' ' . $order->get_currency() ), 1, 0, 'R' );
				$pdf->Cell( 35, 6, utf8_decode( number_format_i18n( $total, 2 ) . ' ' . $order->get_currency() ), 1, 1, 'R' );
			}
		}

		$pdf->Ln( 4 );

		// Totales (solo Factura)
		if ( 'invoice' === $type ) {
			$pdf->SetFont( 'Helvetica', 'B', 9 );
			$pdf->Cell( 125, 6, '', 0, 0 );
			$pdf->Cell( 30, 6, utf8_decode( 'Subtotal:' ), 0, 0, 'R' );
			$pdf->Cell( 35, 6, utf8_decode( number_format_i18n( $order->get_subtotal(), 2 ) . ' ' . $order->get_currency() ), 0, 1, 'R' );

			if ( $order->get_shipping_total() > 0 ) {
				$pdf->Cell( 125, 6, '', 0, 0 );
				$pdf->Cell( 30, 6, utf8_decode( 'Envío:' ), 0, 0, 'R' );
				$pdf->Cell( 35, 6, utf8_decode( number_format_i18n( $order->get_shipping_total(), 2 ) . ' ' . $order->get_currency() ), 0, 1, 'R' );
			}

			if ( $order->get_total_tax() > 0 ) {
				$pdf->Cell( 125, 6, '', 0, 0 );
				$pdf->Cell( 30, 6, utf8_decode( 'Impuestos (IVA):' ), 0, 0, 'R' );
				$pdf->Cell( 35, 6, utf8_decode( number_format_i18n( $order->get_total_tax(), 2 ) . ' ' . $order->get_currency() ), 0, 1, 'R' );
			}

			$pdf->SetFont( 'Helvetica', 'B', 11 );
			$pdf->SetTextColor( 37, 99, 235 );
			$pdf->Cell( 125, 7, '', 0, 0 );
			$pdf->Cell( 30, 7, utf8_decode( 'TOTAL:' ), 0, 0, 'R' );
			$pdf->Cell( 35, 7, utf8_decode( number_format_i18n( $order->get_total(), 2 ) . ' ' . $order->get_currency() ), 0, 1, 'R' );
		}

		// Pie de página
		if ( ! empty( $comp_footer ) ) {
			$pdf->SetY( -20 );
			$pdf->SetFont( 'Helvetica', 'I', 8 );
			$pdf->SetTextColor( 148, 163, 184 );
			$pdf->Cell( 0, 4, utf8_decode( $comp_footer ), 0, 0, 'C' );
		}

		$pdf->Output( 'F', $filepath );
		return $filepath;
	}
}
