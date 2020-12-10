<?php
/**
 * Permissions helper for Duplicate Post.
 *
 * @package Duplicate_Post
 * @since 4.0
 */

namespace Yoast\WP\Duplicate_Post;

/**
 * Represents the Permissions_Helper class.
 */
class Permissions_Helper {

	/**
	 * Returns the array of the enabled post types.
	 *
	 * @return array The array of post types.
	 */
	public function get_enabled_post_types() {
		$duplicate_post_types_enabled = \get_option( 'duplicate_post_types_enabled', [ 'post', 'page' ] );
		if ( ! \is_array( $duplicate_post_types_enabled ) ) {
			$duplicate_post_types_enabled = [ $duplicate_post_types_enabled ];
		}
		return $duplicate_post_types_enabled;
	}

	/**
	 * Determines if post type is enabled to be copied.
	 *
	 * @param string $post_type The post type to check.
	 *
	 * @return bool Whether the post type is enabled to be copied.
	 */
	public function is_post_type_enabled( $post_type ) {
		return \in_array( $post_type, $this->get_enabled_post_types(), true );
	}

	/**
	 * Determines if the current user can copy posts.
	 *
	 * @return bool Whether the current user can copy posts.
	 */
	public function is_current_user_allowed_to_copy() {
		return \current_user_can( 'copy_posts' );
	}

	/**
	 * Determines if the post is a copy intended for Rewrite & Republish.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return bool Whether the post is a copy intended for Rewrite & Republish.
	 */
	public function is_rewrite_and_republish_copy( \WP_Post $post ) {
		return ( \intval( \get_post_meta( $post->ID, '_dp_is_rewrite_republish_copy', true ) ) === 1 );
	}

	/**
	 * Determines if the post has a copy intended for Rewrite & Republish.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return bool Whether the post has a copy intended for Rewrite & Republish.
	 */
	public function has_rewrite_and_republish_copy( \WP_Post $post ) {
		return ( ! empty( \get_post_meta( $post->ID, '_dp_has_rewrite_republish_copy', true ) ) );
	}

	/**
	 * Determines if the post has a copy intended for Rewrite & Republish which is scheduled to be published.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return bool|\WP_Post The scheduled copy if present, false if the post has no scheduled copy.
	 */
	public function has_scheduled_rewrite_and_republish_copy( \WP_Post $post ) {
		$copy_id = \get_post_meta( $post->ID, '_dp_has_rewrite_republish_copy', true );

		if ( ! $copy_id ) {
			return false;
		}

		$copy = \get_post( $copy_id );

		if ( $copy && $copy->post_status === 'future' ) {
			return $copy;
		}

		return false;
	}

	/**
	 * Determines whether the current screen is an edit post screen.
	 *
	 * @return bool Whether or not the current screen is editing an existing post.
	 */
	public function is_edit_post_screen() {
		if ( ! \is_admin() ) {
			return false;
		}

		// Required as get_current_screen is not always available, e.g. for Post_Submitbox::should_change_rewrite_republish_copy.
		if ( ! function_exists( 'get_current_screen' ) ) {
			global $pagenow;
			return $pagenow === 'post.php';
		}

		$current_screen = \get_current_screen();

		return $current_screen->base === 'post' && $current_screen->action !== 'add';
	}

	/**
	 * Determines whether the current screen is an new post screen.
	 *
	 * @return bool Whether or not the current screen is editing an new post.
	 */
	public function is_new_post_screen() {
		if ( ! \is_admin() ) {
			return false;
		}

		// Required as get_current_screen is not always available, e.g. for Post_Submitbox::should_change_rewrite_republish_copy.
		if ( ! function_exists( 'get_current_screen' ) ) {
			global $pagenow;
			return $pagenow === 'post.php';
		}

		$current_screen = \get_current_screen();

		return $current_screen->base === 'post' && $current_screen->action === 'add';
	}

	/**
	 * Determines if we are currently editing a post with Classic editor.
	 *
	 * @return bool Whether we are currently editing a post with Classic editor.
	 */
	public function is_classic_editor() {
		if ( ! $this->is_edit_post_screen() && ! $this->is_new_post_screen() ) {
			return false;
		}

		$screen = \get_current_screen();
		if ( $screen->is_block_editor() ) {
			return false;
		}

		return true;
	}

	/**
	 * Determines if the original post has changed since the creation of the copy.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return bool Whether the original post has changed since the creation of the copy.
	 */
	public function has_original_changed( \WP_Post $post ) {
		if ( ! $this->is_rewrite_and_republish_copy( $post ) ) {
			return false;
		}

		$original               = Utils::get_original( $post );
		$copy_creation_date_gmt = \get_post_meta( $post->ID, '_dp_creation_date_gmt', true );

		if ( $original && $copy_creation_date_gmt ) {
			if ( \strtotime( $original->post_modified_gmt ) > \strtotime( $copy_creation_date_gmt ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determines if duplicate links for the post can be displayed.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return bool Whether the links can be displayed.
	 */
	public function should_link_be_displayed( \WP_Post $post ) {
		return ! $this->is_rewrite_and_republish_copy( $post )
			&& $this->is_current_user_allowed_to_copy()
			&& $this->is_post_type_enabled( $post->post_type );
	}

	/**
	 * Determines whether the passed post type is public and shows an admin bar.
	 *
	 * @param string $post_type The post_type to copy.
	 *
	 * @return bool Whether or not the post can be copied to a new draft.
	 */
	public function post_type_has_admin_bar( $post_type ) {
		$post_type_object = \get_post_type_object( $post_type );

		if ( empty( $post_type_object ) ) {
			return false;
		}

		return $post_type_object->public && $post_type_object->show_in_admin_bar;
	}
}
