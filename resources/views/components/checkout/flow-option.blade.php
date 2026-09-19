<label class="pay-flow-option" :class="{ 'is-selected': paymentMethod === 'flow' }">
    <span class="pay-flow-heading">
        <input type="radio" name="payment_method" value="flow" x-model="paymentMethod" :disabled="!flowCurrencyMatches" aria-describedby="flow-payment-help">
        <span class="pay-flow-name">Flow <span>Pago en línea</span></span>
        <span class="pay-flow-mark" aria-hidden="true">flow</span>
    </span>
    <span id="flow-payment-help" class="pay-flow-help">Elige entre los medios habilitados por esta tienda en Flow.</span>
    <span x-show="flowCurrencyMatches && paymentMethod === 'flow'" class="pay-flow-details">
        <span>Continuarás a Flow para elegir tu medio y completar el pago. Luego podrás volver a tu tienda.</span>
        <strong>Yape y otros medios, según disponibilidad del comercio.</strong>
        @if($store->flow_mode !== 'live')
            <span class="pay-flow-test">Modo de prueba · No uses datos de pago reales.</span>
        @endif
    </span>
    <span x-show="!flowCurrencyMatches" class="pay-flow-help">Disponible al comprar en {{ $store->flow_currency }}. Cambia la moneda de la tienda para usar Flow.</span>
</label>
