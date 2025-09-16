<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if (!function_exists('chld_thm_cfg_locale_css')):
    function chld_thm_cfg_locale_css($uri)
    {
        if (empty($uri) && is_rtl() && file_exists(get_template_directory() . '/rtl.css'))
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter('locale_stylesheet_uri', 'chld_thm_cfg_locale_css');

if (!function_exists('child_theme_configurator_css')):
    function child_theme_configurator_css()
    {
        wp_enqueue_style('chld_thm_cfg_child', trailingslashit(get_stylesheet_directory_uri()) . 'style.css', array('hello-elementor', 'hello-elementor-theme-style', 'hello-elementor-header-footer'));
    }
endif;
add_action('wp_enqueue_scripts', 'child_theme_configurator_css', 10);

// END ENQUEUE PARENT ACTION

// client data
function cv_client_admin_menu()
{
    add_menu_page(
        'Client API Import',
        'Client Import',
        'manage_options',
        'client-api-import',
        'cv_client_import_page',
        'dashicons-database-import',
        20
    );
}
add_action('admin_menu', 'cv_client_admin_menu');


// data fetch

function cv_client_import_page()
{
?>
    <div class="wrap">
        <h1>Import Client from API</h1>
        <input type="text" id="fetch-id" placeholder="405-88-3636" min="1" max="100" style="width:200px;">
        <button id="fetch-api-data" class="button button-primary">Fetch from API</button>

        <form id="client-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="cv_save_client">

            <p><label>ID: <input type="text" name="client_id" id="client_id"></label></p>
            <p>Image: <img id="client_img"></p>
            <input type="hidden" name="client_img_url" id="client_img_url">

            <p><label>Name: <input type="text" name="client_name" id="client_name"></label></p>
            <p><label>Email: <input type="text" name="client_email" id="client_email"></label></p>
            <p><label>Gender: <input type="text" name="client_gender" id="client_gender"></label></p>
            <p><label>Phone: <input type="text" name="client_phone" id="client_phone"></label></p>

            <?php submit_button('Add Client'); ?>
        </form>
    </div>

    <script>
        document.getElementById('fetch-api-data').addEventListener('click', function(e) {
            e.preventDefault();
            let id = document.getElementById('fetch-id').value || 1; // default = 1
            fetch(`https://randomuser.me/api/?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    console.log("data", data)

                    const user = data.results[0]
                    document.getElementById('client_id').value = `${id}`;
                    document.getElementById('client_name').value = user.name.first;
                    document.getElementById('client_gender').value = user.gender;
                    document.getElementById('client_email').value = user.email;
                    document.getElementById('client_phone').value = user.phone;
                    document.getElementById('client_img').src = user.picture.large;
                    document.getElementById('client_img_url').value = user.picture.large;

                });
        });
    </script>
<?php
}


function cv_save_client_data()
{
    if (isset($_POST['client_id'])) {
        $post_id = wp_insert_post([
            'post_type'   => 'client',
            'post_status' => 'publish',
            'post_title'  => sanitize_text_field($_POST['client_name']),
            'meta_input'  => [
                'client_email'     => sanitize_text_field($_POST['client_email']),
                'client_gender' => sanitize_text_field($_POST['client_gender']),
                'client_phone' => sanitize_text_field($_POST['client_phone']),
                'client_id' => sanitize_text_field($_POST['client_id']),
            ]
        ]);

        if ($post_id && !is_wp_error($post_id)) {
            // Handle API image → upload → attach to ACF field
            if (!empty($_POST['client_img_url'])) {
                $image_url = esc_url_raw($_POST['client_img_url']);
                $image_id  = cv_upload_image_from_url($image_url, $post_id);

                if ($image_id) {
                    // Assign to your ACF field (field name: client_image)
                    update_field('client_image', $image_id, $post_id);
                }
            }
        }

        if ($post_id) {
            wp_safe_redirect(admin_url('edit.php?post_type=client'));
            exit;
        }
    }
}
add_action('admin_post_cv_save_client', 'cv_save_client_data');




function cv_upload_image_from_url($image_url, $post_id = 0)
{
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Download image to temp file
    $tmp = download_url($image_url);

    if (is_wp_error($tmp)) {
        return false;
    }

    $file_array = [
        'name'     => basename($image_url),
        'tmp_name' => $tmp,
    ];

    // Upload image
    $image_id = media_handle_sideload($file_array, $post_id);

    // If error, cleanup temp file
    if (is_wp_error($image_id)) {
        @unlink($file_array['tmp_name']);
        return false;
    }

    return $image_id;
}








// 2️⃣ Create a secure custom API endpoint
add_action('rest_api_init', function () {
    register_rest_route('all-events/v1', '/wpevents', [
        'methods' => 'GET',
        'callback' => 'get_protected_events',
        'permission_callback' => function () {
            return is_user_logged_in();  // Authenticated only (via JWT)
        }
    ]);
});

function get_protected_events($data)
{
    $args = [
        'post_type' => 'event',
        'posts_per_page' => -1,
    ];

    $query = new WP_Query($args);


    $events = [];

    while ($query->have_posts()) {
        $query->the_post();

        $terms = get_the_terms(get_the_ID(), 'event-type');

        // var_dump($terms);
        // echo "asfdasd";

        $eventTypes = [];

       

        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $eventTypes = [
                    'name' => $term->name,
                    'slug' => $term->slug
                ];
            }
        } else {
            $eventTypes = "no term found";
        }

        $events[] = [
            'id'             => get_the_ID(),
            'title'          => get_the_title(),
            'content'        => get_the_content(),
            'acf'           => get_fields(),  // ACF fields
            'featured_image' => get_the_post_thumbnail_url(get_the_ID(), 'full'),
            'event_type' => $eventTypes,
        ];
    }

    wp_reset_postdata();

    return rest_ensure_response($events);
}
