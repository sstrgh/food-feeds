<?php
/*
  Plugin Name:  Food Feeds - Feeding your mind
  Plugin URI:   http://food-feeds.com
  Description:  Your favorite food feed
  Version:      1.0
  Author:       Sunny Singh
  Author URI:   http://twitter.com/sstrgh
  License:      GPL-2.0+
*/

class WP_Food_Feeds {

  function __construct() {
    add_action('wp_enqueue_scripts', array($this, 'food_feeds_style'));
    add_action('admin_menu', array($this, 'food_feeds_setup_menu'));

    register_activation_hook( __FILE__, array($this, 'food_feeds_install'));
  }

  public function food_feeds_style( $page ) {
    wp_register_style( 'food-feeds-style', plugins_url('css/food-feeds-style.css', __FILE__));
    wp_enqueue_style( 'food-feeds-style' );
  }

  function food_feeds_setup_menu() {
    add_menu_page('Update Food Feeds', 'Update Food Feeds', 'manage_options', 'update-food-feeds-plugin', array($this, 'update_food_feeds'));
  }

  function update_food_feeds() {
    global $wpdb;
    $table_name = $wpdb->prefix . "foodfeeds_db";

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name';") != $table_name) {
      echo '<h3> DATABASE NOT FOUND!, PLEASE TRY REACTIVATING THIS PLUGIN </h3>';
      return;
    }

    $request = wp_remote_get('http://www.supermiro.com/api/1.0/fetch/jgfb84r1ra', array( 'timeout' => 120, 'httpversion' => '1.1' ));

    if (is_wp_error($request)) {
       echo '<h3> FAILED TO UPDATE FEED. </h3>';
       return;
    }

    $body = wp_remote_retrieve_body($request);
    $data = json_decode($body);
    $total_feeds_received = 0;
    $total_feed_updated = 0;

    if(empty($data)) {
      echo '<h3> FAILED TO UPDATE FEED </h3>';
      return;
    } else {
        foreach($data as $key=>$val) {
          $total_feeds_received++;

          if ($val->startTime){
            $start_time = $val->startTime;
          } else {
            $start_time = '00:00:00';
          }

          $start_date = new DateTime();
          $start_date = $start_date->createFromFormat('d-m-Y H:i:s', $val->startDate . ' ' . $start_time);
          $start_date = $start_date->format('Y-m-d H:i:s');

          $end_date = new DateTime();
          $end_date = $end_date->createFromFormat('d-m-Y H:i:s', $val->endDate . ' ' . '00:00:00');
          $end_date = $end_date->format('Y-m-d H:i:s');

          $rowcount = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE publisher_id = $val->id");

          if ($rowcount > 0) {
            continue;
          }

          $wpdb->insert(
          	$table_name,
          	array(
          		'publisher_id' => $val->id,
          		'title' => $val->title,
              'description' => $val->description,
              'place_name' => $val->placeName,
              'city' => $val->city,
              'event_url' => $val->eventUrl,
              'picture_url' => $val->pictureUrl,
              'event_start_date' => $start_date,
              'event_end_date' => $end_date
          	)
          );

          if ($wpdb->insert_id > 0) {
            $total_feed_updated++;
          }
        }

        if ($total_feed_updated == 0) {
          echo "<h3> Nothing to Update. </h3>";
          return;
        }

        echo "<h3> UPDATE SUCCESSFUL! Updated $total_feed_updated of $total_feeds_received received feeds. </h3>";
    }
  }

  function food_feeds_install() {
    global $wpdb;
    $version = get_option( 'foodfeeds_db_version', '1.0' );

    $table_name = $wpdb->prefix . "foodfeeds_db";

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name';") != $table_name) {
      $sql = "CREATE TABLE $table_name (
        id INT (10) UNSIGNED AUTO_INCREMENT,
        publisher_id INT (10) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        place_name VARCHAR(255),
        city VARCHAR(255),
        event_url VARCHAR(255),
        picture_url VARCHAR(255),
        event_start_date DATETIME,
        event_end_date DATETIME,
        UNIQUE KEY id (id)
      );";

      require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
      dbDelta( $sql );
    }
  }

}

new WP_Food_Feeds();

function food_feeds_shortcode() {
  ob_start();

  global $wpdb;
  $table_name = $wpdb->prefix . "foodfeeds_db";

  if ($wpdb->get_var("SHOW TABLES LIKE '$table_name';") != $table_name) {
    return false;
  }

  $data = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 5");

  if (!$data) {
    return false;
  }

  echo '<div class="food-feed">';

    echo '<div class="food-feed-header">';
      echo '<h6> Here is a list of events you should be looking at: </h6>';
    echo '</div>'; # end of food-feed-header

    echo '<div class="food-feed-body">';

  foreach ($data as $feed_item) {
    echo '<div class="food-feed-item">';
      echo '<div class="food-feed-item-img">';
        echo '<img src="' . esc_url($feed_item->picture_url) . '">' . '</img>';
      echo '</div>'; # end of food-feed-item-img

      echo '<div class="food-feed-item-details">';
        echo '<div>';
          echo '<a class="food-feed-item-title" href="' . esc_url($feed_item->event_url) . '">' . $feed_item->title . '</a>';
        echo '</div>'; # end of food-feed-item-title div

        echo '<div class="food-feed-item-description">';
          echo $feed_item->description;
        echo '</div>'; # end of food-feed-item-description
      echo '</div>'; # end of food-feed-item-details

    echo '</div>'; # end of food-feed-item
    }

    echo '</div>';# end of food-feed-body

    echo '</div>'; # end of food-feed

  $ReturnString = ob_get_contents();
  ob_end_clean();
  return $ReturnString;
}
add_shortcode('foodfeeds', 'food_feeds_shortcode');

?>
