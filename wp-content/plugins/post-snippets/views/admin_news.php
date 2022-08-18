

  <h2 style="margin-top:40px;">More plugins by WPexperts:</h2>

  <style>
  .sab-plugin {
	width: 300px;
	display: inline-block;
	background: #fff;
	border: 1px solid #ccc;
	padding: 4px 4px 20px 4px;
	margin:0 10px 10px 0;
  }
  .sab-plugin img {
	width: 100%;
	height: 200px;
  }
  .sab-plugin p {
	padding: 15px;
	height: 106px;
  }
  .sab-center {
	text-align: center;
  }
  .sab-plugin .sab-button {
	display:inline-block;
  }
  </style>

  <div class="sab-plugins">
	<?php foreach ( $plugins as $plugin ) : ?>
	  <div class="sab-plugin">
		<a target="_blank" href="<?php echo esc_url( $plugin['url'] ); ?>">
		  <img src="<?php echo esc_url( PS_URL ) . 'assets/img/' , esc_attr( $plugin['image'] ); ?>" alt="Go to <?php echo esc_attr( $plugin['name'] ); ?>" />
		</a>
		<p><?php echo esc_html( $plugin['description'] ); ?></p>
		<div class="sab-center">
		  <a target="_blank" class="button button-primary button-hero" href="<?php echo esc_url( $plugin['url'] ); ?>"><?php esc_html_e( 'Read more' ); ?></a>
		</div>
	  </div>
	<?php endforeach ?>
  </div>
</div>
