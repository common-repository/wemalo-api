<div class="wemalo-container">
	
	<h1>Wemalo Plugin</h1>
	<div>
		<a class="wemalo-btn wemalo-btn-default" target="_blank" href="http://www.wemalo.com/">Wemalo</a>
		<a class="wemalo-btn wemalo-btn-primary" target="_blank" href="http://help.wemalo.com/wordpress-wemalo-api-2/">Dokumentation</a>
		<a class="wemalo-btn wemalo-btn-primary" target="_blank" href="http://www.wemalo.com/kontakt/">Kontakt</a>
	</div>
	<h2>Schl&uuml;ssel zur Authentifizierung</h2>
	<div>
		<form method="post">
		<?php $this->set_user_auth_key(); ?>
		<p>
		<span id="authkeyarea_help" class="help-block">Sie erhalten den Authentifizierungsschl&uuml;ssel in Ihren wemalo-connect-Account.</span>
		</p>
			<?php submit_button( 'Speichern', 'primary', 'api_base_url_editor', false ); ?>
			<?php submit_button( 'Token Neu Generieren ', 'secondary', 'generate_new_token', false ); ?>
			<p><br></p>
		</form>

		<ul class="wemalo-list">
			<li>Den Authentifizierungsschl&uuml;ssel erhalten Sie über wemalo-connect.</li>
			<li>In wemalo-connect k&ouml;nnen die einzelnen Calls aktiviert/deaktiviert werden.</li>
			<li>&Uuml;bermittelt werden in der Regel: Produktstammdaten und Bestellungen nach Wemalo; Wareneing&auml;nge, Inventurein- und Inventurausbuchungen, versendete Warenausgangsauftr&auml;ge von Wemalo</li>
			<li style="display:none;">Plugin-URL: <?php print( esc_html( WEMALO_BASE ) ); ?> </li>
		</ul>
	</div>
	<h2>Auftragsstatus</h2>
	<div>
		<form method="post">
		<?php
			$this->set_status_strings();
			submit_button( 'Speichern', 'primary', 'api_base_url_editor' );
		?>
		</form>
		
		<ul class="wemalo-list">
			<li>Sie können die Bezeichnung des jeweiligen Retouren-Status frei vergeben.</li>
			<li>Wenn eine Retoure angemeldet wurde, muss der wc-return-announced-Status gesetzt werden, damit Wemalo die Retouren-Informationen abholt.</li>
			<li>Wurde die Retoure in Wemalo vereinnahmt, wird der Auftragsstatus auf wc-return-booked gesetzt und die Positionen im Auftrag angehängt.</li>
		</ul>
	</div>
	<h2>Checkbox-Label Aufträge</h2>
	<div>
		<form method="post">
			<?php
			$this->set_check_label();
			submit_button( 'Speichern', 'primary', 'api_base_url_editor' );
			?>
		</form>

		<ul class="wemalo-list">
			<li>Sie können hierüber die Bezeichnung für blockierte Aufträge setzen. Diese Bezeichnung wird in Bestellungen neben einer Checkbox angezeigt.</li>
			<li>Wurden Bestellungen noch nicht von Wemalo abgerufen, kann hierüber gesteuert werden, dass die Bestellungen nach dem Download blockiert werden.</li>
		</ul>
	</div>

	<h2>Promi-Versand</h2>
	<div>
		<form method="post">
			<?php
			$this->set_celeb_email();
			submit_button( 'Speichern', 'primary', 'api_base_url_editor' );
			?>
		</form>

		<ul class="wemalo-list">
			<li>Wenn Sie bei Promi-Bestellungen eine besondere Behandlung der Ware wünschen, können Sie hier eine E-Mail-Adresse hinterlegen.</li>
			<li>Ihre Anweisung zur Behandlung der Ware wird an diese E-Mail-Adresse gesendet.</li>
		</ul>
	</div>

	<h2>Custom Fields</h2>
	<div>
		<form method="post">
			<?php
				$this->set_formatted_order_number();
				$this->set_house_number_field();
				$this->set_parent_order_field();
				$this->set_order_category_field();
				submit_button( 'Speichern', 'primary', 'api_base_url_editor' );
			?>
		</form>
		
		<ul class="wemalo-list">
			<li>Über die erweiterten Felder kann ein Matching zwischen Custom Fields und Wemalo-Feldern vorgenommen werden.</li>
			<li>Das Hinterlegen der Custom Fields ist optional.</li>
			<li>Wird bei der Auftragsnummer kein Custom Field für formartierte Auftragsnummern verwendet, nimmt Wemalo die Auftrags-ID.</li>
			<li>Durch Eingaben von Matching-Werten werden keine Custom Fields durch Wemalo angelegt.</li>
		</ul>
	</div>

</div>
<?php

wp_enqueue_style( 'wemalo-css' );

?>
