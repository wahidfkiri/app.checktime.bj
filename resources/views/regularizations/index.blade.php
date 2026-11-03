@extends('layouts.app')

@section('content')
<div id="main" class="layout-navbar navbar-fixed">
    <x-nav-bar />
    <div id="main-content">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-8">
                        <h3>Régularisation de pointage</h3>
                        <p class="text-subtitle text-muted">Coupure d'électricité, oubli de pointage… — ajoute un pointage pour les employés sélectionnés.</p>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="row">
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-header"><h5 class="card-title mb-0">Nouvelle régularisation</h5></div>
                            <div class="card-body">
                                <form id="regForm">
                                    <div class="mb-3">
                                        <label class="form-label">Cause <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <select class="form-control" id="cause_id" name="cause_id" required>
                                                @foreach($causes as $c)
                                                    <option value="{{ $c->id }}">{{ $c->label }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-outline-success" type="button" id="add-cause-btn" title="Ajouter une cause"><i class="bi bi-plus-lg"></i></button>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="reg_date" name="date" value="{{ date('Y-m-d') }}" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Heure d'arrivée <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control" id="check_in" name="check_in" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Heure de départ <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control" id="check_out" name="check_out" required>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">Employés <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control mb-1" id="emp_search" placeholder="Filtrer par code / nom…">
                                        <select class="form-control" id="employee_ids" name="employee_ids[]" multiple size="10" required>
                                            @foreach($employees as $e)
                                                <option value="{{ $e['id'] }}" data-search="{{ strtolower($e['emp_code'].' '.$e['full_name']) }}">
                                                    {{ $e['emp_code'] }} — {{ $e['full_name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Ctrl/Cmd + clic pour sélectionner plusieurs employés.</small>
                                    </div>

                                    <div id="conflict-panel" class="alert alert-warning d-none">
                                        <strong><i class="bi bi-exclamation-triangle me-1"></i> Pointages déjà existants ce jour-là :</strong>
                                        <ul class="mb-0 mt-1" id="conflict-list"></ul>
                                    </div>

                                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Appliquer</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header"><h5 class="card-title mb-0">Régularisations récentes</h5></div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead><tr><th>Employé</th><th>Date</th><th>Horaire</th><th>Cause</th></tr></thead>
                                        <tbody>
                                        @forelse($recent as $r)
                                            <tr>
                                                <td>{{ $r->employee->emp_code ?? '—' }}</td>
                                                <td>{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($r->check_in)->format('H:i') }}–{{ \Carbon\Carbon::parse($r->check_out)->format('H:i') }}</td>
                                                <td><span class="badge bg-info">{{ $r->cause->label ?? '—' }}</span></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-center text-muted py-2">Aucune régularisation.</td></tr>
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
    var R = {
        check: "{{ route('authorizations.regularizations.check') }}",
        store: "{{ route('authorizations.regularizations.store') }}",
        causeStore: "{{ route('authorizations.regularizations.causes.store') }}"
    };
    function toast(icon, title){ Swal.fire({icon:icon,title:title,toast:true,position:'top-end',showConfirmButton:false,timer:3500,timerProgressBar:true}); }

    var lastConflicts = {}; // employee_id => info

    // Filtre employés
    $('#emp_search').on('input', function(){
        var q = this.value.toLowerCase();
        $('#employee_ids option').each(function(){
            $(this).toggle(($(this).data('search')||'').indexOf(q) !== -1);
        });
    });

    function selectedEmployees(){ return $('#employee_ids').val() || []; }

    // Test temps réel des conflits
    var timer = null;
    function checkConflicts(){
        var ids = selectedEmployees(), date = $('#reg_date').val();
        if (!date || ids.length === 0) { $('#conflict-panel').addClass('d-none'); lastConflicts = {}; return; }
        $.ajax({ url:R.check, type:'POST', data:{_token:CSRF, date:date, employee_ids:ids},
            success:function(r){
                lastConflicts = r.conflicts || {};
                var keys = Object.keys(lastConflicts);
                if (keys.length === 0){ $('#conflict-panel').addClass('d-none'); $('#conflict-list').empty(); return; }
                var html = '';
                keys.forEach(function(id){
                    var opt = $('#employee_ids option[value="'+id+'"]').text().trim();
                    var c = lastConflicts[id];
                    html += '<li>'+opt+' — pointage : '+(c.check_in||'?')+(c.check_out?('–'+c.check_out):'')+' ('+(c.status||'')+')</li>';
                });
                $('#conflict-list').html(html);
                $('#conflict-panel').removeClass('d-none');
            }
        });
    }
    $('#reg_date, #employee_ids').on('change', function(){ clearTimeout(timer); timer = setTimeout(checkConflicts, 250); });

    // Ajouter une cause (+)
    $('#add-cause-btn').on('click', function(){
        Swal.fire({ title:'Nouvelle cause', input:'text', inputPlaceholder:'Ex. Panne réseau', showCancelButton:true, confirmButtonText:'Ajouter', cancelButtonText:'Annuler',
            inputValidator:(v)=>(!v||!v.trim())?'Libellé requis':undefined })
        .then(function(res){
            if(!res.isConfirmed) return;
            $.ajax({ url:R.causeStore, type:'POST', data:{_token:CSRF, label:res.value.trim()},
                success:function(r){ if(r.success){ $('#cause_id').append(new Option(r.cause.label, r.cause.id, true, true)); toast('success', r.message);} else { toast('error', r.message||'Erreur'); } },
                error:function(xhr){ toast('error', xhr.responseJSON?.message || 'Erreur'); }
            });
        });
    });

    // Soumission (avec confirmation d'écrasement si conflits)
    $('#regForm').on('submit', function(e){
        e.preventDefault();
        var ids = selectedEmployees();
        if (ids.length === 0){ toast('warning','Sélectionnez au moins un employé'); return; }

        var conflictingSelected = ids.filter(function(id){ return lastConflicts.hasOwnProperty(id); });

        var doSubmit = function(force){
            $.ajax({ url:R.store, type:'POST',
                data:{_token:CSRF, cause_id:$('#cause_id').val(), employee_ids:ids, date:$('#reg_date').val(), check_in:$('#check_in').val(), check_out:$('#check_out').val(), force:force?1:0},
                success:function(r){ if(r.success){ toast('success', r.message); setTimeout(()=>location.reload(),1200);} else { toast('error', r.message||'Erreur'); } },
                error:function(xhr){
                    var m = xhr.responseJSON?.message || 'Erreur';
                    if(xhr.responseJSON?.errors){ m = Object.values(xhr.responseJSON.errors).map(e=>e[0]).join(' '); }
                    toast('error', m);
                }
            });
        };

        if (conflictingSelected.length > 0){
            Swal.fire({
                title:'Pointages existants',
                html: conflictingSelected.length + ' employé(s) sélectionné(s) ont déjà un pointage ce jour-là.<br>Voulez-vous <strong>écraser</strong> ces pointages ?',
                icon:'warning', showCancelButton:true, showDenyButton:true,
                confirmButtonText:'Écraser', denyButtonText:'Ignorer ces employés', cancelButtonText:'Annuler'
            }).then(function(res){
                if(res.isConfirmed) doSubmit(true);      // écraser
                else if(res.isDenied) doSubmit(false);   // ignorer les conflits (skip côté serveur)
            });
        } else {
            doSubmit(false);
        }
    });
});
</script>
@endsection
