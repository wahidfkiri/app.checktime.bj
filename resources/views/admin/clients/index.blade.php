@extends('layouts.app')

@section('content')
<div id="main" class="layout-navbar navbar-fixed">
    <x-nav-bar />
    <div id="main-content">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Administration des clients</h3>
                        <p class="text-subtitle text-muted">Espace super-admin — piloter les clients et entrer dans leur contexte</p>
                    </div>
                </div>
            </div>

            <section class="section">
                {{-- Messages flash (entrer / quitter) --}}
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif

                {{-- Bandeau d'impersonation --}}
                @if($selectedId)
                    @php($ctx = $clients->firstWhere('model.id', $selectedId))
                    <div class="alert alert-warning d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-person-badge me-1"></i> Vous travaillez actuellement dans
                            <strong>{{ $ctx['model']->raison_sociale ?? ('client #'.$selectedId) }}</strong>.</span>
                        <a href="{{ route('admin.clients.release') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-box-arrow-left me-1"></i> Quitter le contexte
                        </a>
                    </div>
                @endif

                {{-- Statistiques --}}
                <div class="row">
                    <div class="col-6 col-lg-4">
                        <div class="card"><div class="card-body text-center">
                            <h4 class="mb-0">{{ $stats['clients'] }}</h4><small class="text-muted">Clients</small>
                        </div></div>
                    </div>
                    <div class="col-6 col-lg-4">
                        <div class="card"><div class="card-body text-center">
                            <h4 class="mb-0 text-success">{{ $stats['active'] }}</h4><small class="text-muted">Actifs</small>
                        </div></div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card"><div class="card-body text-center">
                            <h4 class="mb-0">{{ $stats['employees'] }}</h4><small class="text-muted">Employés (tous clients)</small>
                        </div></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Liste des clients</h4>
                        <button type="button" class="btn btn-success" id="create-client-btn">
                            <i class="bi bi-plus-circle me-1"></i> Nouveau client
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Raison sociale</th>
                                        <th>Connexion</th>
                                        <th class="text-center">Employés</th>
                                        <th class="text-center">Pointages</th>
                                        <th class="text-center">Statut</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($clients as $row)
                                    @php($c = $row['model'])
                                    <tr>
                                        <td>
                                            <div class="fw-bold">{{ $c->raison_sociale }}</div>
                                            <small class="text-muted">{{ $c->email }}</small>
                                        </td>
                                        <td><small>{{ $c->user?->email ?? '—' }}</small></td>
                                        <td class="text-center">{{ $row['employees'] }}</td>
                                        <td class="text-center">{{ $row['attendances'] }}</td>
                                        <td class="text-center">
                                            @if($c->is_active)
                                                <span class="badge bg-success">Actif</span>
                                            @else
                                                <span class="badge bg-secondary">Inactif</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group" role="group">
                                                @if($c->is_active)
                                                    <a href="{{ route('admin.clients.select', $c->id) }}"
                                                       class="btn btn-sm btn-primary" title="Entrer dans ce client">
                                                        <i class="bi bi-box-arrow-in-right"></i> Entrer
                                                    </a>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-info edit-client-btn"
                                                    title="Modifier"
                                                    data-id="{{ $c->id }}"
                                                    data-raison_sociale="{{ $c->raison_sociale }}"
                                                    data-sigle="{{ $c->sigle }}"
                                                    data-rccm="{{ $c->rccm }}"
                                                    data-ifu="{{ $c->ifu }}"
                                                    data-directeur="{{ $c->directeur }}"
                                                    data-email="{{ $c->email }}"
                                                    data-telephone="{{ $c->telephone }}"
                                                    data-adresse="{{ $c->adresse }}"
                                                    data-ville="{{ $c->ville }}"
                                                    data-is_active="{{ $c->is_active ? 1 : 0 }}">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm {{ $c->is_active ? 'btn-warning' : 'btn-success' }} toggle-status-btn"
                                                    data-id="{{ $c->id }}" title="{{ $c->is_active ? 'Désactiver' : 'Activer' }}">
                                                    <i class="bi {{ $c->is_active ? 'bi-toggle-off' : 'bi-toggle-on' }}"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-dark reset-pwd-btn"
                                                    data-id="{{ $c->id }}" data-name="{{ $c->raison_sociale }}" title="Réinitialiser le mot de passe">
                                                    <i class="bi bi-key"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-client-btn"
                                                    data-id="{{ $c->id }}" data-name="{{ $c->raison_sociale }}" title="Supprimer">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">Aucun client enregistré.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

{{-- Modal création --}}
<div class="modal fade" id="createClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Nouveau client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="createClientForm">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Raison sociale *</label><input class="form-control" name="raison_sociale" required></div>
                        <div class="col-md-6"><label class="form-label">Sigle</label><input class="form-control" name="sigle"></div>
                        <div class="col-md-6"><label class="form-label">RCCM *</label><input class="form-control" name="rccm" required></div>
                        <div class="col-md-6"><label class="form-label">IFU</label><input class="form-control" name="ifu"></div>
                        <div class="col-md-6"><label class="form-label">Directeur</label><input class="form-control" name="directeur"></div>
                        <div class="col-md-6"><label class="form-label">Email *</label><input type="email" class="form-control" name="email" required></div>
                        <div class="col-md-6"><label class="form-label">Téléphone</label><input class="form-control" name="telephone"></div>
                        <div class="col-md-6"><label class="form-label">Adresse</label><input class="form-control" name="adresse"></div>
                        <div class="col-12"><hr class="my-2"><small class="text-muted">Accès API biométrique &amp; compte de connexion</small></div>
                        <div class="col-md-12"><label class="form-label">Jeton API (general_token) *</label><input class="form-control" name="general_token" required></div>
                        <div class="col-md-6"><label class="form-label">Email de connexion *</label><input type="email" class="form-control" name="login_user" required></div>
                        <div class="col-md-3"><label class="form-label">Mot de passe *</label><input type="password" class="form-control" name="password_user" required></div>
                        <div class="col-md-3"><label class="form-label">Confirmer *</label><input type="password" class="form-control" name="password_confirmation" required></div>
                    </div>
                    <div class="alert alert-danger d-none mt-2" id="create-errors"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal édition --}}
<div class="modal fade" id="editClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Modifier le client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="editClientForm">
                <input type="hidden" id="edit_id">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Raison sociale *</label><input class="form-control" name="raison_sociale" id="edit_raison_sociale" required></div>
                        <div class="col-md-6"><label class="form-label">Sigle</label><input class="form-control" name="sigle" id="edit_sigle"></div>
                        <div class="col-md-6"><label class="form-label">RCCM *</label><input class="form-control" name="rccm" id="edit_rccm" required></div>
                        <div class="col-md-6"><label class="form-label">IFU</label><input class="form-control" name="ifu" id="edit_ifu"></div>
                        <div class="col-md-6"><label class="form-label">Directeur</label><input class="form-control" name="directeur" id="edit_directeur"></div>
                        <div class="col-md-6"><label class="form-label">Email *</label><input type="email" class="form-control" name="email" id="edit_email" required></div>
                        <div class="col-md-6"><label class="form-label">Téléphone</label><input class="form-control" name="telephone" id="edit_telephone"></div>
                        <div class="col-md-6"><label class="form-label">Ville</label><input class="form-control" name="ville" id="edit_ville"></div>
                        <div class="col-12"><label class="form-label">Adresse</label><input class="form-control" name="adresse" id="edit_adresse"></div>
                    </div>
                    <div class="alert alert-danger d-none mt-2" id="edit-errors"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function () {
    var CSRF = "{{ csrf_token() }}";
    var URL = {
        store: "{{ route('admin.clients.store') }}",
        base:  "{{ url('admin/clients') }}"
    };
    function toast(icon, title){ Swal.fire({icon:icon,title:title,toast:true,position:'top-end',showConfirmButton:false,timer:3000,timerProgressBar:true}); }
    function showErr(box, xhr){
        var msg = 'Une erreur est survenue.';
        if (xhr.responseJSON){
            if (xhr.responseJSON.errors){ msg = Object.values(xhr.responseJSON.errors).map(e=>e[0]).join('<br>'); }
            else if (xhr.responseJSON.message){ msg = xhr.responseJSON.message; }
        }
        box.removeClass('d-none').html(msg);
    }

    // Créer
    $('#create-client-btn').on('click', function(){ $('#create-errors').addClass('d-none'); $('#createClientForm')[0].reset(); new bootstrap.Modal('#createClientModal').show(); });
    $('#createClientForm').on('submit', function(e){
        e.preventDefault(); $('#create-errors').addClass('d-none');
        $.ajax({ url: URL.store, type:'POST', data: $(this).serialize() + '&_token=' + CSRF + '&is_active=1',
            success: function(r){ if(r.success){ toast('success','Client créé'); setTimeout(()=>location.reload(),800);} else { $('#create-errors').removeClass('d-none').html(r.message||'Erreur'); } },
            error: function(xhr){ showErr($('#create-errors'), xhr); }
        });
    });

    // Éditer (préremplissage depuis les data-attributes)
    $('.edit-client-btn').on('click', function(){
        var b = $(this); $('#edit-errors').addClass('d-none');
        $('#edit_id').val(b.data('id'));
        $('#edit_raison_sociale').val(b.data('raison_sociale'));
        $('#edit_sigle').val(b.data('sigle'));
        $('#edit_rccm').val(b.data('rccm'));
        $('#edit_ifu').val(b.data('ifu'));
        $('#edit_directeur').val(b.data('directeur'));
        $('#edit_email').val(b.data('email'));
        $('#edit_telephone').val(b.data('telephone'));
        $('#edit_ville').val(b.data('ville'));
        $('#edit_adresse').val(b.data('adresse'));
        new bootstrap.Modal('#editClientModal').show();
    });
    $('#editClientForm').on('submit', function(e){
        e.preventDefault(); $('#edit-errors').addClass('d-none');
        var id = $('#edit_id').val();
        $.ajax({ url: URL.base + '/' + id, type:'POST',
            data: $(this).serialize() + '&_token=' + CSRF + '&_method=PUT&is_active=1',
            success: function(r){ if(r.success){ toast('success','Client mis à jour'); setTimeout(()=>location.reload(),800);} else { $('#edit-errors').removeClass('d-none').html(r.message||'Erreur'); } },
            error: function(xhr){ showErr($('#edit-errors'), xhr); }
        });
    });

    // Activer / désactiver
    $('.toggle-status-btn').on('click', function(){
        var id = $(this).data('id');
        $.ajax({ url: URL.base + '/' + id + '/toggle-status', type:'POST', data:{_token:CSRF},
            success: function(r){ if(r.success){ toast('success', r.message); setTimeout(()=>location.reload(),600);} },
            error: function(xhr){ toast('error', xhr.responseJSON?.message || 'Erreur'); }
        });
    });

    // Réinitialiser le mot de passe
    $('.reset-pwd-btn').on('click', function(){
        var id = $(this).data('id'), name = $(this).data('name');
        Swal.fire({
            title:'Réinitialiser le mot de passe', html:'Client : <strong>'+name+'</strong><br><small class="text-muted">Laissez vide pour générer un mot de passe.</small>',
            input:'password', inputPlaceholder:'Nouveau mot de passe (optionnel)', showCancelButton:true, confirmButtonText:'Réinitialiser', cancelButtonText:'Annuler'
        }).then(function(res){
            if(!res.isConfirmed) return;
            var data = {_token:CSRF};
            if(res.value){ data.password = res.value; data.password_confirmation = res.value; }
            $.ajax({ url: URL.base + '/' + id + '/mot-de-passe', type:'POST', data:data,
                success: function(r){ if(r.success){ Swal.fire({icon:'success',title:'Mot de passe modifié',html:'Compte : <strong>'+r.data.email+'</strong><br>Mot de passe : <code>'+r.data.password+'</code><br><small class="text-muted">Affiché une seule fois.</small>'});} },
                error: function(xhr){ toast('error', xhr.responseJSON?.message || 'Erreur'); }
            });
        });
    });

    // Supprimer (impact + confirmation par nom)
    $('.delete-client-btn').on('click', function(){
        var id = $(this).data('id'), name = $(this).data('name');
        $.get(URL.base + '/' + id + '/impact-suppression', function(r){
            if(!r.success){ toast('error', r.message||'Erreur'); return; }
            var c = r.data.counts;
            var lignes = Object.keys(c).map(k => '<tr><th class="text-capitalize">'+k+'</th><td class="text-end">'+c[k]+'</td></tr>').join('');
            Swal.fire({
                title:'Supprimer « '+name+' » ?', icon:'warning',
                html:'<p class="text-danger">Suppression irréversible.</p><table class="table table-sm">'+lignes+'</table>'
                     +'<p class="small">Recopiez la raison sociale pour confirmer :</p>',
                input:'text', inputPlaceholder: name, showCancelButton:true, confirmButtonColor:'#d33', confirmButtonText:'Supprimer définitivement', cancelButtonText:'Annuler'
            }).then(function(res){
                if(!res.isConfirmed) return;
                $.ajax({ url: URL.base + '/' + id, type:'POST', data:{_token:CSRF, _method:'DELETE', confirmation:res.value},
                    success: function(r){ if(r.success){ toast('success', r.message); setTimeout(()=>location.reload(),800);} else { toast('error', r.message||'Erreur'); } },
                    error: function(xhr){ toast('error', xhr.responseJSON?.message || 'Erreur'); }
                });
            });
        }).fail(function(xhr){ toast('error', xhr.responseJSON?.message || 'Erreur'); });
    });
});
</script>
@endsection
