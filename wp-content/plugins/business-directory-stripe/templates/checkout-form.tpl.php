<?php
$months = array(
    '01' => _x( 'Jan', 'months', 'wpbdp-stripe' ),
    '02' => _x( 'Feb', 'months', 'wpbdp-stripe' ),
    '03' => _x( 'Mar', 'months', 'wpbdp-stripe' ),
    '04' => _x( 'Apr', 'months', 'wpbdp-stripe' ),
    '05' => _x( 'May', 'months', 'wpbdp-stripe' ),
    '06' => _x( 'Jun', 'months', 'wpbdp-stripe' ),
    '07' => _x( 'Jul', 'months', 'wpbdp-stripe' ),
    '08' => _x( 'Aug', 'months', 'wpbdp-stripe' ),
    '09' => _x( 'Sep', 'months', 'wpbdp-stripe' ),
    '10' => _x( 'Oct', 'months', 'wpbdp-stripe' ),
    '11' => _x( 'Nov', 'months', 'wpbdp-stripe' ),
    '12' => _x( 'Dec', 'months', 'wpbdp-stripe' ),
);
?>
<form action="<?php echo $url; ?>" method="post" id="wpbdp-stripe-form" class="wpbdp-cc-form">
    <input type="hidden" name="_stripeKey" value="<?php echo $key; ?>" />
    <input type="hidden" name="stripeToken" value="" />

    <h4><?php _ex( 'Credit Card Details', 'checkout form', 'wpbdp-stripe'); ?></h4>
    <p><?php _ex( 'Please enter your credit card details below.', 'checkout form', 'wpbdp-stripe' ); ?></p>

    <div class="wpbdp-msg error stripe-errors" style="display:none;"></div>

    <table class="wpbdp-cc-fields">
        <tr class="wpbdp-cc-field cc-number">
            <td scope="row">
                <label for="wpbdp-cc-field-number"><?php _ex( 'Card Number:', 'checkout form', 'wpbdp-stripe' ); ?></label>
            </td>
            <td>
                <input type="text" id="wpbdp-cc-field-number" size="25" data-stripe="number" />
            </td>
        </tr>
        <tr class="wpbdp-cc-field cc-exp">
            <td scope="row">
                <label for="wpbdp-cc-field-exp"><?php _ex( 'Expiration Date (MM/YYYY):', 'checkout form', 'wpbdp-stripe' ); ?></label>
            </td>
            <td>
                <select id="wpbdp-cc-field-exp" data-stripe="exp-month">
                    <?php foreach ( $months as $month => $name ): ?>
                        <option value="<?php echo $month; ?>"><?php echo $month; ?> - <?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select> /
                <!--<input type="text" size="2" data-stripe="exp-month" /> /-->
                <input type="text" size="8" data-stripe="exp-year" />
            </td>
        </tr>
        <tr class="wpbdp-cc-field cc-cvc">
            <td scope="row">
                <label for="wpbdp-cc-field-cvc"><?php _ex( 'CVC:', 'checkout form', 'wpbdp-stripe' ); ?></label>
            </td>
            <td>
                <input type="text" id="wpbdp-cc-field-cvc" size="8" data-stripe="cvc" />
            </td>
        </tr>
    </table>

    <input type="submit" value="<?php _ex( 'Submit Payment', 'checkout form', 'wpbdp-stripe' ); ?>" class="button submit" />
</form>
