@extends('layouts.app')

@section('content')
<div id="main" class="layout-navbar navbar-fixed">
    <x-nav-bar />
    <div id="main-content">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Types de congés</h3>
                        <p class="text-subtitle text-muted">Référentiel des types de congés du client</p>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="row">
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header"><h5 class="card-title mb-0">Ajouter un type</h5></div>
                            <div class="card-body">
                                <form id="createForm">
                                    <div class="mb-3">
                                        <label class="form-label">Nom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="new_name" name="name" maxlength="100" required>
                                        <div class="invalid-feedback" id="new_name-error"></div>
                                    </div>
                                    <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i> Ajouter</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header"><h5 class="card-title mb-0">Liste</h5></div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead><tr><th>Nom</th><th class="text-center">Statut</th><th class="text-end">Actions</th></tr></thead>
                                        <tbody id="types-body">
                                            @forelse($types as $t)
                                            <tr data-id="{{ $t->id }}">
                                                <td class="type-name">{{ $t->name }}</td>
                                                <td class="text-center">
                                                    @if($t->is_active)
                                                        <span class="badge bg-success">Actif</span>
                                                    @else
                                                        <span class="badge bg-secondary">Inactif</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-warning edit-btn" data-id="{{ $t->id }}" data-name="{{ $t->name }}" title="Renommer"><i class="bi bi-pencil"></i></button>
                                                        <button class="btn btn-sm {{ $t->is_active ? 'btn-secondary' : 'btn-success' }} toggle-btn" data-id="{{ $t->id }}" title="{{ $t->is_active ? 'Désactiver' : 'Activer' }}"><i class="bi bi-toggle-{{ $t->is_active ? 'off' : 'on' }}"></i></button>
                                                        <button class="btn btn-sm btn-danger delete-btn" data-id="{{ $t->id }}" data-name="{{ $t->name }}" title="Supprimer"><i class="bi bi-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr id="empty-row"><td colspan="3" class="text-center text-muted py-3">Aucun type de congé.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
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
    var BASE = "{{ url('leave-types') }}";
    function toast(icon, title){ Swal.fire({icon:icon,title:title,toast:true,position:'top-end',showConfirmButton:false,timer:3000,timerProgressBar:true}); }

    $('#createForm').on('submit', function(e){
        e.preventDefault(); $('#new_name-error').text(''); $('#new_name').removeClass('is-invalid');
        $.ajax({ url: BASE, type:'POST', data:{_token:CSRF, name:$('#new_name').val()},
            success:function(r){ if(r.success){ toast('success', r.message); setTimeout(()=>location.reload(),700);} else { toast('error', r.message||'Erreur'); } },
            error:function(xhr){
                if(xhr.status===422 && xhr.responseJSON?.errors?.name){ $('#new_name-error').text(xhr.responseJSON.errors.name[0]); $('#new_name').addClass('is-invalid'); }
                else { toast('error', xhr.responseJSON?.message || 'Erreur'); }
            }
        });
    });

    $(document).on('click', '.edit-btn', function(){
        var id = $(this).data('id'), current = $(this).data('name');
        Swal.fire({ title:'Renommer', input:'text', inputValue:current, showCancelButton:true, confirmButtonText:'Enregistrer', cancelButtonText:'Annuler',
            inputValidator:(v)=>(!v||!v.trim())?'Le nom est requis':undefined })
        .then(function(res){
            if(!res.isConfirmed) return;
            $.ajax({ url: BASE + '/' + id, type:'POST', data:{_token:CSRF, _method:'PUT', name:res.value.trim()},
                success:function(r){ if(r.success){ toast('success', r.message); setTimeout(()=>location.reload(),700);} else { toast('error', r.message||'Erreur'); } },
                error:function(xhr){ toast('error', xhr.responseJSON?.message || 'Erreur'); }
            });
        });
    });

    $(document).on('click', '.toggle-btn', function(){
        var id = $(this).data('id');
        $.ajax({ url: BASE + '/' + id + '/toggle-status', type:'POST', data:{_token:CSRF},
            success:function(r){ if(r.success){ toast('success', r.message); setTimeout(()=>location.reload(),500);} else { toast('error', r.message||'Erreur'); } },
            error:function(xhr){ toast('error', xhr.responseJSON?.message || 'Erreur'); }
        });
    });

    $(document).on('click', '.delete-btn', function(){
        var id = $(this).data('id'), name = $(this).data('name');
        Swal.fire({ title:'Supprimer ?', html:'Type : <strong>'+name+'</strong>', icon:'warning', showCancelButton:true, confirmButtonColor:'#d33', confirmButtonText:'Supprimer', cancelButtonText:'Annuler' })
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
