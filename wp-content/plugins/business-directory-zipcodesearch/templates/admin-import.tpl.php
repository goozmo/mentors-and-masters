<?php echo wpbdp_admin_header( __( 'Import ZIP Code Database', 'wpbdp-zipcodesearch' ), 'zip-db-import' ); ?>
<?php echo wpbdp_admin_notices(); ?>

<?php if ( $import ): ?>
<div class="import-step-2">
<h3><?php _e( '3. Database Import', 'wpbdp-zipcodesearch' ); ?></h3>
<table class="import-status">
    <tbody>
        <tr>
            <th scope="row"><?php _ex( 'File', 'import', 'wpbdp-zipcodesearch' ); ?></th>
            <td><?php echo basename( $import->get_filepath() ); ?></td>            
        </tr>
        <tr>
            <th scope="row">
                <?php _ex( 'Database', 'import', 'wpbdp-zipcodesearch' ); ?>
            </th>
            <td><?php echo $import->get_database_name(); ?></td>
        </tr>
        <tr>            
            <th scope="row"><?php _ex( 'Revision', 'import', 'wpbdp-zipcodesearch' ); ?></th>
            <td><?php echo $import->get_database_date(); ?></td>            
        </tr>            
        <tr>
            <th scope="row">
                <?php _ex( 'Items', 'import', 'wpbdp-zipcodesearch' ); ?>
            </th>
            <td>
                <?php echo number_format( $import->get_total_items() ); ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php _ex( 'Progress', 'import', 'wpbdp-zipcodesearch' ); ?></th>
            <td class="progress">
                <div class="import-progress">
                    <span class="progress-text"><?php echo $import->get_progress( '%' ); ?>%</span>
                    <div class="progress-bar"><div class="progress-bar-outer"><div class="progress-bar-inner" style="width: <?php echo round( $import->get_progress( '%' ) ); ?>%"></div></div></div>
                </div>
                <div class="import-status-text">
                    <?php if ( $import->get_progress( 'n' ) == 0 ): ?>
                        <?php _ex( 'Import has not started.', 'import', 'wpbdp-zipcodesearch' ); ?>
                    <?php else: ?>
                        <?php _ex( 'Import paused.', 'import', 'wpbdp-zipcodesearch' ); ?>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <tr class="actions">
            <td colspan="2">
                <a href="#" class="resume-import button button-primary">
                    <?php if ( $import->get_imported() == 0 ): ?>
                        <?php _ex( 'Start Import', 'import', 'wpbdp-zipcodesearch' ); ?>
                    <?php else: ?>
                        <?php _ex( 'Resume Import', 'import', 'wpbdp-zipcodesearch' ); ?>                        
                    <?php endif; ?>
                </a>
                <a href="<?php echo add_query_arg( 'cancel_import', 1 ); ?>" class="cancel-import"><?php _ex( 'Cancel Import', 'import', 'wpbdp-zipcodesearch' ); ?></a>
            </td>
        </tr>
    </tbody>
</table>
</div>

<div class="import-step-3" style="display: none;">
<h3><?php _e( '3. Database imported successfully!', 'wpbdp-zipcodesearch' ); ?></h3>
<?php if ( strtolower( basename( $import->get_filepath() ) ) == 'zipcodes.db' ): ?>
<div class="wpbdp-note"><p><?php _ex( 'Please delete the "zipcodes.db" file from your "db/" directory since it is no longer needed.', 'import', 'wpbdp-zipcodesearch' ); ?></p></div>
<?php endif; ?>
<p><?php
    printf( _x( 'The ZIP code database <strong>%s</strong> has been imported and is ready to be used.', 'import', 'wpbdp-zipcodesearch' ),
            $import->get_database_name() . ' ' . $import->get_database_date() );
?></p>
<p><?php _e( 'You can now:', 'wpbdp-zipcodesearch' ); ?>
    <ul>
        <li><a href="<?php echo admin_urL( 'admin.php?page=wpbdp_admin_settings&groupid=zipsearch' ); ?>"><?php _e( 'Configure ZIP Search options', 'wpbdp-zipsearch' ); ?></a></li>
        <li><a href="http://businessdirectoryplugin.com/zip-databases/" target="_blank"><?php _e( 'Download/install additional databases', 'wpbdp-zipcodesearch' ); ?></a></li>
        <li><a href="<?php echo admin_urL( 'admin.php?page=wpbdp_admin_settings' ); ?>"><?php _e( '← Return to Business Directory Admin', 'wpbdp-zipcodesearch' ); ?></a></li>
    </ul>
</p>
</div>


<?php else: ?>
<div class="import-step-1">
<?php if ( $upgrade_possible ): ?>
<div class="updated">
    <p>
        <?php _ex( "Business Directory has detected you have an old-style database setup (zipcodes.db). If you wish so, Business Directoy can migrate this ZIP code information to the new format so you don't need to download and install a new database.", 'import', 'wpbdp-zipcodesearch' ); ?>
    </p>
    <p>
        <a href="<?php echo add_query_arg( 'nomigrate', 1); ?>" class="button"><?php _ex( 'Ignore the file. I want to import a new database.', 'import', 'wpbdp-zipcodesearch' ); ?></a>
        <a href="<?php echo add_query_arg( 'migrate', 1 ); ?>" class="button button-primary"><?php _ex( 'Proceed with migration', 'import', 'wpbdp-zipcodesearch' ); ?></a>
    </p>
</div>
<?php endif; ?>
    
<h3><?php
    echo sprintf( _x( '1. Download one or more databases from %s', 'wpbdp-zipcodesearch' ),
                  '<a href="http://businessdirectoryplugin.com/zip-databases/" target="_blank">BusinessDirectoryPlugin.com</a>' ); ?>
</h3>
    
<div class="columns-wrapper">
    <div class="col-1">
        <h3><?php _e( '2. Upload database file(s)', 'wpbdp-zipcodesearch' ); ?></h3>        
        <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="MAX_FILE_SIZE" value="31457280" />
        <table class="form-table">    
            <tbody>
                <tr>
                    <th scope="row">
                        <?php _ex( 'Database File', 'import', 'wpbdp-zipcodesearch' ); ?>
                    </th>
                    <td class="form-required">
                        <input type="file" name="dbfile" /> 
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <input type="submit" id="submit" class="button button-primary" value="<?php _ex( 'Upload File', 'import', 'wpbdp-zipcodesearch' ); ?>"/>
        </p>
        </form>
    </div>
    <div class="col-2">
        <?php if ( $dbfiles ): ?>
        <h3><?php _e( '... or choose a manually uploaded database file', 'wpbdp-zipcodesearch' ); ?></h3>                
            <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="POST" enctype="multipart/form-data">
                <?php foreach ( $dbfiles as &$dbfile ): ?>
                <label>
                    <input type="radio" name="uploaded_dbfile" value="<?php echo $dbfile['filepath']; ?>">
                    <b><?php echo basename( $dbfile['filepath'] ); ?></b> - <?php echo $dbfile['database']; ?>  (<?php printf( _x( 'Version %s', 'import', 'wpbdp-zipcodesearch' ), $dbfile['date'] ); ?>)
                </label><br />
                <?php endforeach; ?>          
                <p class="submit">
                    <input type="submit" id="submit" class="button button-primary" value="<?php _ex( 'Use this file', 'import', 'wpbdp-zipcodesearch' ); ?>"/>
                </p>                
            </form>
        <?php else: ?>
            <div class="wpbdp-note">
                <p><?php printf( _x( 'You can manually upload database files via FTP or similar if the upload form does not work for you due to upload restrictions on your host. Please use the "%s" folder inside the plugin to store these files and reload this page.', 'import', 'wpbdp-zipcodesearch' ), '<b>db</b>' ); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

</div>
<?php endif; ?>

<?php echo wpbdp_admin_footer(); ?>
