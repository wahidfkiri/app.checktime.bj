@extends('layouts.app')

@section('content')
<div id="main" class="layout-navbar navbar-fixed">
    <x-nav-bar />
    <div id="main-content">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Notifications — Configuration globale</h3>
                        <p class="text-subtitle text-muted">SMTP et API SMS de la plateforme (super-admin)</p>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="row">
                    {{-- SMTP --}}
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header"><h5 class="card-title mb-0">✉️ Serveur SMTP</h5></div>
                            <div class="card-body">
                                <form id="smtpForm">
                                    <div class="row g-2">
                                        <div class="col-md-8">
                                            <label class="form-label">Serveur SMTP *</label>
                                            <input class="form-control" name="mail_host" value="{{ $mail['mail_host'] }}" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Port *</label>
                                            <input type="number" class="form-control" name="mail_port" value="{{ $mail['mail_port'] ?: 587 }}" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Chiffrement</label>
                                            <select class="form-control" name="mail_encryption">
                                                @foreach(['' => 'Aucun', 'tls' => 'TLS', 'ssl' => 'SSL', 'starttls' => 'STARTTLS'] as $v => $lbl)
                                                    <option value="{{ $v }}" @selected($mail['mail_encryption'] === $v)>{{ $lbl }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">Utilisateur</label>
                                            <input class="form-control" name="mail_username" value="{{ $mail['mail_username'] }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Mot de passe</label>
                                            <input type="password" class="form-control" name="mail_password" placeholder="{{ $mail['mail_password'] ? '•••••• (inchangé si vide)' : '' }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Expéditeur (email) *</label>
                                            <input type="email" class="form-control" name="mail_from_address" value="{{ $mail['mail_from_address'] }}" required>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Expéditeur (nom)</label>
                                            <input class="form-control" name="mail_from_name" value="{{ $mail['mail_from_name'] }}">
                                        </div>
                                    </div>
                                    <div class="mt-3 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                                        <button type="button" class="btn btn-outline-secondary" id="testSmtpBtn">Tester l'envoi</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- SMS --}}
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header"><h5 class="card-title mb-0">📱 API SMS</h5></div>
                            <div class="card-body">
                                <form id="smsForm">
                                    <label class="form-label">Clé API SMS *</label>
                                    <input class="form-control" name="sms_api_key" value="{{ $smsApiKey }}" required>
                                    <div class="mt-3 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                                        <button type="button" class="btn btn-outline-secondary" id="testSmsBtn">Tester</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function () {
    var CSRF = "{{ csrf_token() }}";
    var R = {
        smtp: "{{ route('admin.notifications.smtp') }}",
        smtpTest: "{{ route('admin.notifications.smtp.test') }}",
        sms: "{{ route('admin.notifications.sms') }}",
        smsTest: "{{ route('admin.notifications.sms.test') }}"
    };
    function toast(icon, title){ Swal.fire({icon:icon,title:title,toast:true,position:'top-end',showConfirmButton:false,timer:3500,timerProgressBar:true}); }
    function post(url, data){ return $.ajax({url:url,type:'POST',data:$.extend({_token:CSRF}, data)}); }

    $('#smtpForm').on('submit', function(e){
        e.preventDefault();
        post(R.smtp, $(this).serializeArray().reduce((o,f)=>{o[f.name]=f.value;return o;},{}))
            .done(r=>toast('success', r.message))
            .fail(xhr=>toast('error', xhr.responseJSON?.message || 'Erreur'));
    });

    $('#testSmtpBtn').on('click', function(){
        Swal.fire({title:'Email de test', input:'email', inputPlaceholder:'destinataire@exemple.com', showCancelButton:true, confirmButtonText:'Envoyer', cancelButtonText:'Annuler',
            inputValidator:(v)=>(!v)?'Email requis':undefined})
        .then(function(res){
            if(!res.isConfirmed) return;
            var data = $('#smtpForm').serializeArray().reduce((o,f)=>{o[f.name]=f.value;return o;},{});
            data.test_email = res.value;
            Swal.showLoading();
            post(R.smtpTest, data).done(r=>toast(r.success?'success':'error', r.message)).fail(xhr=>toast('error', xhr.responseJSON?.message || 'Échec du test'));
        });
    });

    $('#smsForm').on('submit', function(e){
        e.preventDefault();
        post(R.sms, {sms_api_key:$('[name=sms_api_key]').val()})
            .done(r=>toast('success', r.message))
            .fail(xhr=>toast('error', xhr.responseJSON?.message || 'Erreur'));
    });

    $('#testSmsBtn').on('click', function(){
        post(R.smsTest, {sms_api_key:$('[name=sms_api_key]').val()})
            .done(r=>toast(r.success?'success':'error', r.message))
            .fail(xhr=>toast('error', xhr.responseJSON?.message || 'Échec du test'));
    });
});
</script>
@endsection
