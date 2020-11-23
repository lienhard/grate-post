<?php
/*
** Adds rating system to post of any type on WordPress.
** Place grate-post.php in your theme directory, along
** with ./css/grate-post.css and ./js/grate-post.js.
** Add this to your theme functions.php file:
** include_once('grate-post.php');
**
*/

define ('GRATE_POST_NONCE', '6fJ$E[z6e^`q(G^j');
define ('GRATE_POST_TOTALS', 'gratePostTotals');
define ('GRATE_POST_RATING', 'gratePostRating');

class GRate_Post {

    var $postTypes = array('post'); // array of post types to show rating on
    var $mustLogin = false;         // set to true if you only want this to work for logged in users
	
	function __construct() {

        add_action('wp_ajax_gratePostAjax', array($this, 'doAjax') ); 

        add_action('wp_ajax_nopriv_gratePostAjax', array($this, 'doAjax') );

        add_action('logPostRating', array($this, 'logPostRating'));
        
        add_shortcode('gp_show', array($this, 'ratingShow'));

        add_shortcode('gp_show_form', array($this, 'ratingShowForm'));

        add_action ('wp_enqueue_scripts', array($this, 'enqueueScript'));

        /*
        **  Comment out the_content filter below if you want to place the
        **  rating form and display using the shortcodes
        ** [gp_show] and [gp_show_form]
        */
        add_filter('the_content', array($this, 'theContent' ));

        /* for development purposes, use these query parms */

        /*

        if (isset($_GET['gpxall'])) {
            add_action('wp', array($this, 'clear'));
        }

        if (isset($_GET['gpd'])) {
            add_action('wp', array($this, 'dump'));
        }

        if (isset($_GET['gpx'])) {
            add_action('wp', array($this, 'clearUser'));
        }

        */
    }
    
    function exception($msg) {
		
        throw new Exception("GRate_Post: ". $msg); 
    }
	
	function showVar($var, $echo = true) {
		
		if ($echo) 
			echo '<pre>'.print_r($var, true).'</pre>';
		else
			return '<pre>'.print_r($var, true).'</pre>';
    }
    
    function clear() { // helper function to clear

        global $post;

        delete_post_meta( $post->ID, GRATE_POST_TOTALS);
    }

    function clearUser() {

        global $post; $postid = $post->ID;

        $totals = get_post_meta( $postid, GRATE_POST_TOTALS, true );

        if (empty($totals)) {

            //echo 'nothing to clear'; exit;
            return;
        }

        $num = !empty($totals['num']) ? $totals['num'] : 0;
        $sum = !empty($totals['sum']) ? $totals['sum'] : 0;

        if ($num == 0) {

            //echo 'nothing to clear'; exit;
            return;
        }

        $user = get_current_user_id();

        if (!empty($totals['users'][$user])) {
           $oldRating = $totals['users'][$user];
           $sum -= $oldRating;
           unset($totals['users'][$user]);
           $num--;
        } else {
            //echo 'nothing to clear'; exit;
            return;
        }
        $totals['num'] = $num;
        $totals['sum'] = $sum;

        update_post_meta( $postid, GRATE_POST_TOTALS, $totals );

        //echo 'review cleared for user '.$user; exit;
    }

    function dump() { // helper funcion to dump

        global $post;

        $meta = get_post_meta( $post->ID, GRATE_POST_TOTALS);

        $this->showVar($meta); exit;
    }
	
	function doAjax() {

        $post = filter_input_array(INPUT_POST);
		
        $nonce =  $post['_ajax_nonce'];
		
		if ( ! wp_verify_nonce( $nonce, GRATE_POST_NONCE ) ) {
			
			die( 'Security check' ); 
        }
		
		$action = $post['userAction'];
		
		$args = $post['args'];
		
		do_action($action, $args);
		
		die();
		
    }

    function updateTotals($user, $postid, $rating) {

        $totals = get_post_meta( $postid, GRATE_POST_TOTALS, true );

        if (empty($totals)) {
            
            $totals = array('num'=>0,'sum'=>0,'users'=>array());
        }

        $num = !empty($totals['num']) ? $totals['num'] : 0;
        $sum = !empty($totals['sum']) ? $totals['sum'] : 0;

        if ($rating == 0 && !empty($totals['users'][$user])) {
            $oldRating = $totals['users'][$user];
            $sum -= $oldRating;
            unset($totals['users'][$user]);
            $num--;
        } elseif (!empty($totals['users'][$user])) {
           $oldRating = $totals['users'][$user];
           $sum -= $oldRating;
           $totals['users'][$user] = $rating;
           $sum += $rating;
        } elseif ($rating > 0) {
            $totals['users'][$user] = $rating;
            $sum += $rating;
            $num++;
        }

        $totals['num'] = $num;
        $totals['sum'] = $sum;

        update_post_meta( $postid, GRATE_POST_TOTALS, $totals );

        //$this->showVar($totals);

        if (empty($num)) return;

        $average = round( $sum / $num, 1 );

        update_post_meta( $postid, GRATE_POST_RATING, $average );

    }

    function getUserRating($user) {

        global $post;

        if (is_user_logged_in()) {
            $user = get_current_user_id();
        }

        $totals = get_post_meta( $post->ID, GRATE_POST_TOTALS, true );

        if (!empty($totals['users'][$user])) {

            return $totals['users'][$user];
        }

        return 0;
    }
    
    function logPostRating($args) {

        $rating = $args['rating'];
        $postid = $args['postid'];
        $user = $args['user'];

        if ($user == 0) $user = get_current_user_id(); // use user id when logged in, else uniqueid

        $this->updateTotals($user, $postid, $rating);

        echo $this->ratingShow(array('postid'=>$postid, 'wrap'=>false));
    }

    function getRating($postid) {

        $totals = get_post_meta( $postid, GRATE_POST_TOTALS, true );

        $num = !empty($totals['num']) ? $totals['num'] : 0;
        $sum = !empty($totals['sum']) ? $totals['sum'] : 0;

        if (empty($num)) return false;

        return array('average'=>round( $sum / $num, 1 ), 'total'=>$num);
    }

    function createNonce() {
		
		return wp_create_nonce( GRATE_POST_NONCE );
		
    }
    
    function enqueueScript () {

        global $post;

		$url = admin_url( 'admin-ajax.php' );
		$nonce = $this->createNonce();
        $user = is_user_logged_in() ? 0 :  uniqid();
        $rating = $this->getUserRating($user);

		$vars = array(
			'url' 	=> $url,
			'nonce'	=> $nonce,
            'user'	=> $user,
            'postid' => $post->ID,
            'rating' => $rating
		);

		$json = json_encode($vars);
		
		wp_enqueue_script( 'grate-post', get_stylesheet_directory_uri() . '/js/grate-post.js', array(), false, TRUE );

        wp_add_inline_script( 'grate-post', '/* < ![CDATA[ */ var gpVarsJson = \'' . $json . '\'; /* ]]> */', 'before' );
        
        wp_enqueue_style( 'grate-post', get_template_directory_uri() . '/css/grate-post.css');
	}

    // Create the rating interface.

    function ratingShowForm () {
        
        global $post;

        if ($this->mustLogin && !is_user_logged_in()) return '';

        ob_start();

        ?>
        <fieldset class="comments-rating">
            <label>Rate this Post: </label>
            <span class="rating-container">
                <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                    <input type="radio" id="rating-<?php echo esc_attr( $i ); ?>" name="rating" value="<?php echo esc_attr( $i ); ?>" />
                    <label for="rating-<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></label>
                <?php endfor; ?>
                <input type="radio" id="rating-0" class="star-cb-clear" name="rating" value="0" /><label for="rating-0">Clear</label>
            </span>
        </fieldset>
       
        <?php

        return ob_get_clean();
    }

    // Display the average rating

    function ratingShow($atts) {

        global $post;

        if ($this->mustLogin && !is_user_logged_in()) return '';

        $atts = shortcode_atts( array(
            'postid' 	=> $post->ID,
            'wrap'      => true,
		), $atts, 'Rate_Post' );

        $postid = $atts['postid'];
        $wrap = $atts['wrap'];

        if ( false === $this->getRating( $postid ) ) {
            return $wrap ? '<div class="grate-post-rating"></div>' : '';
        }	
            
        $stars   = '';
        $ratings = $this->getRating( $postid );
        $average = $ratings['average'];

        for ( $i = 1; $i <= $average + 1; $i++ ) {
            
            $width = intval( $i - $average > 0 ? 20 - ( ( $i - $average ) * 20 ) : 20 );

            if ( 0 === $width ) {
                continue;
            }

            $stars .= '<span style="overflow:hidden; width:' . $width . 'px" class="dashicons dashicons-star-filled"></span>';

            if ( $i - $average > 0 ) {
                global $leftOffset;
                $leftOffset = 'position:relative; left:-' . $width . 'px';
                $stars .= '<span style="overflow:hidden; position:relative; left:-' . $width .'px;" class="dashicons dashicons-star-empty"></span>';
            }
        }
        
        $emptyStars = 5 - $average;
        $emptyStars = floor($emptyStars);
        for ( $i = 1; $i <= $emptyStars; $i++ ) {
            global $leftOffset;
            $stars .= '<span style="overflow:hidden; width:20px; ' . $leftOffset . ' " class="dashicons dashicons-star-empty"></span>';
        }
        
        
        $totalRatings = $ratings['total'];
        $custom_content  = '<div class="rating bg-grey"><div class="average-rating">' . $stars . ' <span class="rating-text">Ratings(' . $totalRatings . ')</span></div></div>';

        if ($wrap) {
            return '<div class="grate-post-rating">'.$custom_content.'</div>';
        } else {
            return $custom_content;
        }
        
    }

    function theContent($content) {

        global $post;

        if (!in_array($post->post_type, $this->postTypes)) return $content;

        $rating = $this->ratingShow(array());
        $form = $this->ratingShowForm();

        return $rating. $content . $form;
    }
}

$gratePost = new GRate_Post();
