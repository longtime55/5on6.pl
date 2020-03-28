<div class="row payment-plugin" id="payuPayment" style="display: none;">
    <div class="col-md-10 col-sm-12 box-center center mt-4 mb-0">
        <div class="row">
            
            <div class="col-xl-12 text-center">
                <img class="img-fluid" src="{{ url('images/payu/payment.png') }}" title="{{ trans('payu::messages.Payment with PayU') }}">
            </div>
    
            <!-- ... -->
        
        </div>
    </div>
</div>

@section('after_scripts')
    @parent
    <script>
        $(document).ready(function ()
        {
            var selectedPackage = $('input[name=package_id]:checked').val();
            var packagePrice = getPackagePrice(selectedPackage);
            var paymentMethod = $('#paymentMethodId').find('option:selected').data('name');
    
            /* Check Payment Method */
            checkPaymentMethodForPayu(paymentMethod, packagePrice);
            
            $('#paymentMethodId').change(function () {
                paymentMethod = $(this).find('option:selected').data('name');
                checkPaymentMethodForPayu(paymentMethod, packagePrice);
            });
            $('.package-selection').click(function () {
                selectedPackage = $(this).val();
                packagePrice = getPackagePrice(selectedPackage);
                paymentMethod = $('#paymentMethodId').find('option:selected').data('name');
                checkPaymentMethodForPayu(paymentMethod, packagePrice);
            });
    
            /* Send Payment Request */
            $('#submitPostForm').on('click', function (e)
            {
                e.preventDefault();
        
                paymentMethod = $('#paymentMethodId').find('option:selected').data('name');
                
                if (paymentMethod != 'payu' || packagePrice <= 0) {
                    return false;
                }
        
                $('#postForm').submit();
        
                /* Prevent form from submitting */
                return false;
            });
        });

        function checkPaymentMethodForPayu(paymentMethod, packagePrice)
        {
            if (paymentMethod == 'payu' && packagePrice > 0) {
                $('#payuPayment').show();
            } else {
                $('#payuPayment').hide();
            }
        }
    </script>
@endsection
