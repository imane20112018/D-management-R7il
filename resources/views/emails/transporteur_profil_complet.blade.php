<h2>Un transporteur a complété son profil !</h2>

<p><strong>Nom :</strong> {{ $transporteur->nom }}</p>
<p><strong>Email :</strong> {{ $transporteur->email }}</p>
<p><strong>Véhicule :</strong> {{ $transporteur->vehicule ?? 'Non renseigné' }}</p>
<p><strong>Téléphone :</strong> {{ $transporteur->telephone ?? 'Non renseigné' }}</p>

<p>👉 Vérifiez-le dans le dashboard admin.</p>
