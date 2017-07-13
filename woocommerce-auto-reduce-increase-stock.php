<?php
/**
 * Plugin Name: WooCommerce Auto Reduce/Increase Stock
 * Description: This plugin reduces the inventory when new order processes, and restock products when the order status change to Cancelled.
 * Author: Everton Strack
 * Author URI: https://evertonstrack.com.br
 * Version: 1.0.4
 */

/*  Copyright 2017 - Everton Strack */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WC_Auto_Reduce_Increase_Stock' ) ) {

	/**
	* WC_Auto_Reduce_Increase_Stock main class.
	*/
	class WC_Auto_Reduce_Increase_Stock {


		/**
		 * Initialize the plugin public actions.
		 */
		public function __construct() {	
			// Change order status for hold-on
			add_action( 'woocommerce_checkout_order_processed', array( $this,'change_order_status' ), 10, 1 );

			// Reduce order stock when order its processed.
			add_action( 'woocommerce_order_status_on-hold', array( $this,'reduce_order_stock' ), 10, 1 );
			// add_action( 'woocommerce_order_status_processing', array( $this,'reduce_order_stock' ), 10, 1 );

			// Adiciona nota para o cliente sobre o pagamento recebido e desconta do estoque
			add_action( 'woocommerce_order_status_processing', array( $this,'add_note_payment_done' ), 10, 1 ); 

			// Block Woocommerce reduce order stock when payment complete.
			add_filter( 'woocommerce_payment_complete_reduce_order_stock', array( $this, '__return_false'), 10, 1 );

			// Increase order stock when changing the order status to cancelled.
			add_action( 'woocommerce_order_status_processing_to_cancelled', array( $this, 'restore_order_stock' ), 10, 1 );
			add_action( 'woocommerce_order_status_completed_to_cancelled', array( $this, 'restore_order_stock' ), 10, 1 );
			add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'restore_order_stock' ), 10, 1 );
			add_action( 'woocommerce_order_status_processing_to_refunded', array( $this, 'restore_order_stock' ), 10, 1 );
		}

		/*
		*	Restore or stock
		*
		* @param int $order_id Order ID.
		*/
		public function restore_order_stock ( $order_id ) {
			$order = new WC_Order( $order_id );
			if ( 'yes' === get_option( 'woocommerce_manage_stock' ) && $order && 0 < count( $order->get_items() ) ) {
				
				foreach ( $order->get_items() as $item ) {
					$product_id = $item['product_id'];

					if ( 0 < $product_id ) {
						$product = $order->get_product_from_item( $item );

						if ( $product && $product->exists() && $product->managing_stock() ) {

							$old_stock = $product->stock;
							$quantity = apply_filters( 'woocommerce_order_item_quantity', $item['qty'], $order, $item );
							$new_stock = $product->increase_stock( $quantity );
							$item_name = $product->get_sku() ? $product->get_sku() : $item['product_id'];

							do_action( 'woocommerce_auto_stock_restored', $product, $item );

							$order->add_order_note( sprintf( __( 'Estoque do produto %1$s foi restaurado para %2$s.', 'woocommerce' ), $item_name, $new_stock) );

							$order->send_stock_notifications( $product, $new_stock, $item['qty'] );
						}
					}
				}
			}
		}

		/**
		* Adiciona nota para o cliente sobre o pagamento recebido
		*	define the woocommerce_order_status_processing callback 
		* @param int $order_id Order ID.
		*/
		public function add_note_payment_done( $order_id ) {
			//$this->reduce_order_stock( $order_id );
			$order = new WC_Order( $order_id );
			$order->add_order_note( 'Pagamento confirmado. O status do seu pedido será alterado para "Em produção". O prazo de produção varia de 5 a 10 dias úteis.', 1 );
		}

		/**
		* Reduce order stock.
		*
		* @param int $order_id Order ID.
		*/
		public function reduce_order_stock( $order_id ) {
		
			$gateway = get_post_meta( $order_id, '_payment_method' );

			if($gateway[0] == 'pagseguro'){
				$order = new WC_Order( $order_id );
				$order->add_order_note('PagSeguro: O comprador iniciou a transação, mas até o momento o PagSeguro não recebeu nenhuma informação sobre o pagamento. Status do pedido será alterado de Pagamento pendente para Aguardando e o estoque será reduzido automaticamente.');
				$order->reduce_order_stock();
			}

		}

		public function change_order_status( $order_id ) {
		
			$order = new WC_Order( $order_id );
			$gateway = get_post_meta( $order_id, '_payment_method' );
			if($gateway[0] == 'pagseguro'){
				if($order->status == 'pending'){
					$order->update_status('on-hold');	
				}
			}
		}


		
	}

	$GLOBALS['auto_reduce_increase_stock'] = new WC_Auto_Reduce_Increase_Stock();

}
