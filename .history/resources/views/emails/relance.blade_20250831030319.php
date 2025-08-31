@component('mail::message')
# Bonjour {{ $nom }},

Votre abonnement **{{ $type }}** a expiré le **{{ $date_fin }}**.

Pensez à renouveler votre abonnement pour continuer à profiter de nos services 🚛.

@component('mail::button', ['url' => config('app.url')])
Renouveler maintenant
@endcomponent

Merci,<br>
{{ config('app.name') }}
@endcomponent
