<?php

namespace SearchRegex\Source\Plugin;

use SearchRegex\Source;
use SearchRegex\Result;
use SearchRegex\Sql;
use SearchRegex\Schema;
use SearchRegex\Filter;

/**
 * Source: Redirection items
 */
class Redirection extends Source\Source {
	public function get_actions( Result $result ) {
		$edit = admin_url( 'tools.php?page=redirection.php' );

		return [
			'edit' => $edit,
		];
	}

	public function get_table_id() {
		return 'id';
	}

	public function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'redirection_items';
	}

	public function get_title_column() {
		return 'url';
	}

	public function save( $row_id, array $changes ) {
		$redirect = $this->get_columns_to_change( $changes );

		if ( count( $redirect ) > 0 ) {
			// XXX
			$item = \Red_Item::get_by_id( $row_id );

			if ( ! $item instanceof \WP_Error ) {
				$this->log_save( 'redirect', array_merge( [ 'id' => $row_id ], $redirect ) );

				$json = $item->to_json();
				$json = array_merge( $json, $redirect );

				$result = true;

				/** @psalm-suppress UndefinedFunction */
				if ( Plugin\searchregex_can_save() ) {
					$result = $item->update( $json );
				}

				if ( $result ) {
					return true;
				}
			}

			return new \WP_Error( 'searchregex', 'Failed to update redirection' );
		}

		return true;
	}

	public function delete_row( $row_id ) {
		$this->log_save( 'delete redirection', $row_id );

		/** @psalm-suppress UndefinedFunction */
		if ( Plugin\searchregex_can_save() ) {
			/**
			 * @psalm-suppress UndefinedMethod
			 */
			if ( Red_Item::delete( $row_id ) ) {
				return true;
			}

			return new \WP_Error( 'searchregex_delete', 'Failed to delete comment', 401 );
		}

		return true;
	}

	public function get_filter_preload( $schema, $filter ) {
		global $wpdb;

		/** @psalm-suppress DocblockTypeContradiction */
		if ( $schema['column'] === 'group_id' && $filter instanceof Filter\Filter_Member ) {
			$preload = [];

			foreach ( $filter->get_values() as $value ) {
				$group = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}redirection_groups WHERE id=%d", $value ) );

				if ( $group ) {
					$preload[] = [
						'label' => $group,
						'value' => $schema['column'] . '_' . (string) intval( $value, 10 ),
					];
				}
			}

			return $preload;
		}

		return [];
	}

	public function autocomplete( $column, $value ) {
		global $wpdb;

		if ( $column['column'] === 'url' ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT url as id,url as value FROM {$wpdb->prefix}redirection_items WHERE url LIKE %s LIMIT 50", '%' . $wpdb->esc_like( $value ) . '%' ) );
		}

		if ( $column['column'] === 'group_id' ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT id as id, name as value FROM {$wpdb->prefix}redirection_groups WHERE name LIKE %s LIMIT 50", '%' . $wpdb->esc_like( $value ) . '%' ) );
		}

		return [];
	}

	public function get_schema() {
		global $wpdb;

		return [
			'name' => __( 'Redirection', 'search-regex' ),
			'table' => $wpdb->prefix . 'redirection_items',
			'columns' => [
				[
					'column' => 'id',
					'type' => 'integer',
					'title' => __( 'ID', 'search-regex' ),
					'modify' => false,
				],
				[
					'column' => 'url',
					'type' => 'string',
					'title' => __( 'Source URL', 'search-regex' ),
					'options' => 'api',
					'global' => true,
				],
				[
					'column' => 'last_count',
					'type' => 'integer',
					'title' => __( 'Hit Count', 'search-regex' ),
				],
				[
					'column' => 'last_access',
					'type' => 'date',
					'title' => __( 'Last Access', 'search-regex' ),
				],
				[
					'column' => 'group_id',
					'type' => 'member',
					'title' => __( 'Group', 'search-regex' ),
					'options' => 'api',
					'preload' => true,
				],
				[
					'column' => 'status',
					'type' => 'member',
					'title' => __( 'Status', 'search-regex' ),
					'options' => [
						[
							'value' => 'enabled',
							'label' => __( 'Enabled', 'search-regex' ),
						],
						[
							'value' => 'disabled',
							'label' => __( 'Disabled', 'search-regex' ),
						],
					],
				],
				[
					'column' => 'position',
					'type' => 'integer',
					'title' => __( 'Position', 'search-regex' ),
				],
				[
					'column' => 'action_code',
					'type' => 'member',
					'title' => __( 'HTTP Code', 'search-regex' ),
					'options' => $this->get_http_codes(),
				],
				[
					'column' => 'title',
					'type' => 'string',
					'title' => __( 'Title', 'search-regex' ),
					'global' => true,
				],
				// [
				// 	'column' => 'action_type',
				// 	'type' => 'member',
				// 	'title' => __( 'Action Type', 'search-regex' ),
				// 	'options' => $this->get_action_types(),
				// ],
				// [
				// 	'column' => 'match_type',
				// 	'type' => 'member',
				// 	'title' => __( 'Match Type', 'search-regex' ),
				// 	'options' => $this->get_match_types(),
				// ],
				[
					'column' => 'action_data',
					'type' => 'string',
					'title' => __( 'Target', 'search-regex' ),
					'global' => true,
				],
			],
		];
	}

	/**
	 * Get action types
	 *
	 * @return array
	 */
	private function get_action_types() {
		/**
		 * @psalm-suppress UndefinedClass
		 */
		$types = Red_Action::available();
		$actions = [];

		foreach ( array_keys( $types ) as $type ) {
			/**
			 * @psalm-suppress UndefinedClass
			 */
			$obj = Red_Action::create( $type, 301 );
			$actions[] = [
				'value' => $type,
				'label' => $obj->name(),
			];
		}

		return $actions;
	}

	/**
	 * Get match types
	 *
	 * @return array
	 */
	private function get_match_types() {
		/**
		 * @psalm-suppress UndefinedClass
		 */
		$types = Red_Match::available();
		$actions = [];

		foreach ( array_keys( $types ) as $type ) {
			/**
			 * @psalm-suppress UndefinedClass
			 */
			$obj = Red_Match::create( $type );
			$actions[] = [
				'value' => $type,
				'label' => $obj->name(),
			];
		}

		return $actions;
	}

	/**
	 * Get all supported HTTP codes
	 *
	 * @return array
	 */
	private function get_http_codes() {
		$codes = [ 301, 302, 303, 304, 307, 308, 400, 401, 403, 404, 410, 418, 451, 500, 501, 502, 503, 504 ];
		$http = [];

		foreach ( $codes as $code ) {
			$http[] = [
				'value' => "$code",
				'label' => "$code",
			];
		}

		return $http;
	}

	public function convert_result_value( Schema\Column $schema, $value ) {
		if ( $schema->get_column() === 'group_id' ) {
			/**
			 * @psalm-suppress UndefinedClass
			 */
			$group = Red_Group::get( $value );
			if ( $group ) {
				return $group->get_name();
			}
		}

		if ( $schema->get_column() === 'last_access' && $value === '1970-01-01 00:00:00' ) {
			return __( 'Not accessed', 'search-regex' );
		}

		return parent::convert_result_value( $schema, $value );
	}
}

/**
 * Source: Redirection groups
 */
// phpcs:ignore
class Redirection_Groups_Search_Regex extends Source\Source {
	public function get_table_id() {
		return 'id';
	}

	public function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'redirection_groups';
	}

	public function get_title_column() {
		return 'name';
	}

	public function save( $row_id, array $changes ) {
		$redirect = $this->get_columns_to_change( $changes );

		if ( count( $redirect ) > 0 ) {
			/** @psalm-suppress UndefinedClass */
			$item = \Red_Group::get( $row_id );

			if ( ! is_wp_error( $item ) ) {
				$this->log_save( 'redirect', array_merge( [ 'id' => $row_id ], $redirect ) );

				$json = $item->to_json();
				$json = array_merge( $json, $redirect );

				$result = true;

				/** @psalm-suppress UndefinedFunction */
				if ( Plugin\searchregex_can_save() ) {
					$result = $item->update( $json );
				}

				if ( $result ) {
					return true;
				}
			}

			return new \WP_Error( 'searchregex', 'Failed to update redirection group' );
		}

		return true;
	}

	public function delete_row( $row_id ) {
		$this->log_save( 'delete redirection group', $row_id );

		/** @psalm-suppress UndefinedFunction */
		if ( Plugin\searchregex_can_save() ) {
			/** @psalm-suppress UndefinedClass */
			if ( Red_Group::delete( $row_id ) ) {
				return true;
			}

			return new \WP_Error( 'searchregex_delete', 'Failed to delete comment', 401 );
		}

		return true;
	}

	public function autocomplete( $column, $value ) {
		global $wpdb;

		if ( isset( $column['column'] ) && $column['column'] === 'name' ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT name as id,name as value FROM {$wpdb->prefix}redirection_groups WHERE name LIKE %s LIMIT 50", '%' . $wpdb->esc_like( $value ) . '%' ) );
		}

		return [];
	}

	public function get_schema() {
		global $wpdb;

		if ( class_exists( '\Redirection\Module\Module' ) ) {
			/** @psalm-suppress UndefinedClass */
			$wp_id = \Redirection\Module\WordPress::MODULE_ID;

			/** @psalm-suppress UndefinedClass */
			$apache_id = \Redirection\Module\Apache::MODULE_ID;

			/** @psalm-suppress UndefinedClass */
			$nginx_id = \Redirection\Module\Nginx::MODULE_ID;
		} else {
			/** @psalm-suppress UndefinedClass */
			$wp_id = WordPress_Module::MODULE_ID;

			/** @psalm-suppress UndefinedClass */
			$apache_id = Apache_Module::MODULE_ID;

			/** @psalm-suppress UndefinedClass */
			$nginx_id = Nginx_Module::MODULE_ID;
		}

		return [
			'name' => __( 'Redirection Groups', 'search-regex' ),
			'table' => $wpdb->prefix . 'redirection_groups',
			'columns' => [
				[
					'column' => 'id',
					'type' => 'integer',
					'title' => __( 'ID', 'search-regex' ),
					'modify' => false,
				],
				[
					'column' => 'name',
					'type' => 'string',
					'title' => __( 'Name', 'search-regex' ),
					'options' => 'api',
					'global' => true,
				],
				[
					'column' => 'module_id',
					'type' => 'member',
					'title' => __( 'Module', 'search-regex' ),
					'options' => [
						[
							'value' => $wp_id,
							'label' => __( 'WordPress', 'search-regex' ),
						],
						[
							'value' => $apache_id,
							'label' => __( 'Apache', 'search-regex' ),
						],
						[
							'value' => $nginx_id,
							'label' => __( 'Nginx', 'search-regex' ),
						],
					],
				],
				[
					'column' => 'status',
					'type' => 'member',
					'title' => __( 'Status', 'search-regex' ),
					'options' => [
						[
							'value' => 'enabled',
							'label' => __( 'Enabled', 'search-regex' ),
						],
						[
							'value' => 'disabled',
							'label' => __( 'Disabled', 'search-regex' ),
						],
					],
				],
			],
		];
	}
}

add_filter( 'searchregex_sources_plugin', function( $plugins ) {
	// Only show if Redirection is loaded
	if ( defined( 'REDIRECTION_VERSION' ) ) {
		$plugins[] = [
			'name' => 'redirection',
			'label' => __( 'Redirection', 'search-regex' ),
			'class' => 'Redirection_Search_Regex',
			'type' => 'plugin',
		];
		$plugins[] = [
			'name' => 'redirection-groups',
			'label' => __( 'Redirection Groups', 'search-regex' ),
			'class' => 'Redirection_Groups_Search_Regex',
			'type' => 'plugin',
		];
	}

	return $plugins;
} );
