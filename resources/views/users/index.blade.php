@extends('layouts.app')

@section('content')
<div id="main" class="layout-navbar navbar-fixed">
    <x-nav-bar />
    <div id="main-content">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Utilisateurs</h3>
                        <p class="text-subtitle text-muted">Comptes d'accès du client et leurs permissions</p>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Liste des utilisateurs</h5>
                        <button type="button" class="btn btn-success" id="create-btn">
                            <i class="bi bi-plus-circle me-1"></i> Nouvel utilisateur
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr><th>Nom</th><th>Email</th><th class="text-center">Accès</th><th class="text-end">Actions</th></tr>
                                </thead>
                                <tbody>
                                @forelse($users as $u)
                                    <tr>
                                        <td class="fw-bold">{{ $u->name }}</td>
                                        <td>{{ $u->email }}</td>
                                        <td class="text-center">
                                            @if($u->hasRole('client'))
                                                <span class="badge bg-primary">Administrateur</span>
                                            @else
                                                <span class="badge bg-info">Restreint</span>
                                                <small class="text-muted">({{ $u->permissions->count() }} droit(s))</small>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-warning edit-btn" data-id="{{ $u->id }}" title="Modifier"><i class="bi bi-pencil"></i></button>
                                                @if($u->id !== auth()->id())
                                                <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $u->id }}" data-name="{{ $u->name }}" title="Supprimer"><i class="bi bi-trash"></i></button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-3">Aucun utilisateur.</td></tr>
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

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">Nouvel utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm">
                <input type="hidden" id="user_id">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback" id="name-error"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback" id="email-error"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mot de passe <span id="pwd-req" class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password">
                            <div class="form-text" id="pwd-hint">Min. 8 caractères.</div>
                            <div class="invalid-feedback" id="password-error"></div>
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin">
                        <label class="form-check-label" for="is_admin"><strong>Administrateur du client</strong> (accès complet)</label>
                    </div>

                    <div id="permissions-section" class="mt-3">
                        <label class="form-label">Permissions (accès restreint)</label>
                        <div class="row">
                            @foreach($permissionGroups as $group => $perms)
                            <div class="col-md-6 mb-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="fw-bold small text-muted mb-1">{{ $group }}</div>
                                    @foreach($perms as $permName => $permLabel)
                                    <div class="form-check">
                                        <input class="form-check-input perm-check" type="checkbox" value="{{ $permName }}" id="perm_{{ $permName }}">
                                        <label class="form-check-label small" for="perm_{{ $permName }}">{{ $permLabel }}</label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
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
    var BASE = "{{ url('users') }}";
    function toast(icon, title){ Swal.fire({icon:icon,title:title,toast:true,position:'top-end',showConfirmButton:false,timer:3000,timerProgressBar:true}); }
    function clearErrors(){ $('.invalid-feedback').text(''); $('.form-control').removeClass('is-invalid'); }

    function togglePermsSection(){
        if ($('#is_admin').is(':checked')) { $('#permissions-section').slideUp(); }
        else { $('#permissions-section').slideDown(); }
    }
    $('#is_admin').on('change', togglePermsSection);

    $('#create-btn').on('click', function(){
        clearErrors(); $('#userForm')[0].reset(); $('#user_id').val('');
        $('.perm-check').prop('checked', false);
        $('#userModalLabel').text('Nouvel utilisateur');
        $('#pwd-req').show(); $('#pwd-hint').text('Min. 8 caractères.');
        togglePermsSection();
        new bootstrap.Modal('#userModal').show();
    });

    $(document).on('click', '.edit-btn', function(){
        var id = $(this).data('id');
        $.get(BASE + '/' + id, function(r){
            if(!r.success){ toast('error', r.message||'Erreur'); return; }
            clearErrors(); $('#userForm')[0].reset(); $('.perm-check').prop('checked', false);
            $('#user_id').val(r.data.id);
            $('#name').val(r.data.name);
            $('#email').val(r.data.email);
            $('#is_admin').prop('checked', !!r.data.is_admin);
            (r.data.permissions||[]).forEach(function(p){ $('#perm_'+p).prop('checked', true); });
            $('#userModalLabel').text('Modifier l\'utilisateur');
            $('#pwd-req').hide(); $('#pwd-hint').text('Laisser vide pour conserver le mot de passe actuel.');
            togglePermsSection();
            new bootstrap.Modal('#userModal').show();
        }).fail(function(){ toast('error','Utilisateur non trouvé'); });
    });

    $('#userForm').on('submit', function(e){
        e.preventDefault(); clearErrors();
        var id = $('#user_id').val();
        var perms = $('.perm-check:checked').map(function(){ return this.value; }).get();
        var payload = {
            _token: CSRF,
            name: $('#name').val(),
            email: $('#email').val(),
            password: $('#password').val(),
            is_admin: $('#is_admin').is(':checked') ? 1 : 0,
            permissions: perms
        };
        var url = BASE + (id ? '/' + id : '');
        if (id) payload._method = 'PUT';
        $.ajax({ url:url, type:'POST', data:payload,
            success:function(r){ if(r.success){ bootstrap.Modal.getInstance(document.getElementById('userModal')).hide(); toast('success', r.message); setTimeout(()=>location.reload(),700);} else { toast('error', r.message||'Erreur'); } },
            error:function(xhr){
                if(xhr.status===422 && xhr.responseJSON?.errors){
                    Object.keys(xhr.responseJSON.errors).forEach(function(k){ $('#'+k+'-error').text(xhr.responseJSON.errors[k][0]); $('#'+k).addClass('is-invalid'); });
                } else { toast('error', xhr.responseJSON?.message || 'Erreur'); }
            }
        });
    });

    $(document).on('click', '.delete-btn', function(){
        var id = $(this).data('id'), name = $(this).data('name');
        Swal.fire({ title:'Supprimer ?', html:'Utilisateur : <strong>'+name+'</strong>', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', confirmButtonText:'Supprimer', cancelButtonText:'Annuler' })
        .then(function(res){
            if(!res.isConfirmed) return;
            $.ajax({ url: BASE + '/' + id, type:'POST', data:{_token:CSRF, _method:'DELETE'},
                success:function(r){ if(r.success){ toast('success', r.message); setTimeout(()=>location.reload(),600);} else { toast('error', r.message||'Erreur'); } },
                error:function(xhr){ toast('error', xhr.responseJSON?.message || 'Erreur'); }
            });
        });
    });
});
</script>
@endsection
