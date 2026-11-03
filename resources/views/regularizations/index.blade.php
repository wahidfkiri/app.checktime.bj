@extends('layouts.app')

@section('content')
<div id="main" class="layout-navbar navbar-fixed">
    <x-nav-bar />
    <div id="main-content">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-8 order-md-1 order-last">
                        <h3>Régularisation de pointage</h3>
                        <p class="text-subtitle text-muted">Coupure d'électricité, oubli de pointage… — ajoute un pointage pour les employés sélectionnés.</p>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-12">
                                <button type="button" class="btn btn-success" id="create-reg-btn" data-bs-toggle="modal" data-bs-target="#regModal">
                                    <i class="bi bi-plus-circle me-1"></i> Nouvelle régularisation
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Employé</th>
                                        <th>Date</th>
                                        <th>Horaire</th>
                                        <th>Cause</th>
                                        <th>Enregistré le</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($recent as $r)
                                    <tr>
                                        <td class="fw-bold">{{ $r->employee->emp_code ?? '—' }}</td>
                                        <td>{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($r->check_in)->format('H:i') }} – {{ \Carbon\Carbon::parse($r->check_out)->format('H:i') }}</td>
                                        <td><span class="badge bg-info">{{ $r->cause->label ?? '—' }}</span></td>
                                        <td><small class="text-muted">{{ $r->created_at?->format('d/m/Y H:i') }}</small></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">Aucune régularisation enregistrée.</td></tr>
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

<!-- Modal : nouvelle régularisation -->
<div class="modal fade" id="regModal" tabindex="-1" aria-labelledby="regModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="regModalLabel">Nouvelle régularisation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="regForm">
                @csrf
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="cause_id" class="form-label">Cause <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-control" id="cause_id" name="cause_id" required>
                                    @foreach($causes as $c)
                                        <option value="{{ $c->id }}">{{ $c->label }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-success" type="button" id="add-cause-btn" title="Ajouter une cause"><i class="bi bi-plus-lg"></i></button>
                            </div>
                            <div class="invalid-feedback" id="cause_id-error"></div>
                        </div>
                        <div class="col-md-2">
                            <label for="reg_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="reg_date" name="date" value="{{ date('Y-m-d') }}" required>
                            <div class="invalid-feedback" id="date-error"></div>
                        </div>
                        <div class="col-md-2">
                            <label for="check_in" class="form-label">Arrivée <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="check_in" name="check_in" required>
                            <div class="invalid-feedback" id="check_in-error"></div>
                        </div>
                        <div class="col-md-2">
                            <label for="check_out" class="form-label">Départ <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="check_out" name="check_out" required>
                            <div class="invalid-feedback" id="check_out-error"></div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>Employés <span class="text-danger">*</span></span>
                            <span class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="check-all">
                                <label class="form-check-label small" for="check-all">Tout cocher</label>
                            </span>
                        </label>
                        <input type="text" class="form-control form-control-sm mb-2" id="emp_search" placeholder="Filtrer par code / nom…">
                        <div class="border rounded p-2" style="max-height: 260px; overflow-y:auto;" id="emp-list">
                            @foreach($employees as $e)
                                <div class="form-check emp-item" data-search="{{ strtolower($e['emp_code'].' '.$e['full_name']) }}">
                                    <input class="form-check-input emp-check" type="checkbox" value="{{ $e['id'] }}" id="emp_{{ $e['id'] }}">
                                    <label class="form-check-label" for="emp_{{ $e['id'] }}">
                                        <strong>{{ $e['emp_code'] }}</strong> — {{ $e['full_name'] }}
                                        <span class="conflict-tag text-danger small d-none"> · pointage existant</span>
                                    </label>
                                </div>
                            @endforeach
                            @if(count($employees) === 0)
                                <div class="text-muted small">Aucun employé.</div>
                            @endif
                        </div>
                        <small class="text-muted"><span id="selected-count">0</span> employé(s) sélectionné(s).</small>
                    </div>

                    <div id="conflict-panel" class="alert alert-warning d-none mb-0">
                        <strong><i class="bi bi-exclamation-triangle me-1"></i> Pointages déjà existants ce jour-là :</strong>
                        <ul class="mb-0 mt-1" id="conflict-list"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="submit-reg">
                        <span id="submit-reg-text"><i class="bi bi-check2-circle me-1"></i> Appliquer</span>
                        <span id="submit-reg-spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                    </button>
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
    var R = {
        check: "{{ route('authorizations.regularizations.check') }}",
        store: "{{ route('authorizations.regularizations.store') }}",
        causeStore: "{{ route('authorizations.regularizations.causes.store') }}"
    };
    function toast(icon, title){ Swal.fire({icon:icon,title:title,toast:true,position:'top-end',showConfirmButton:false,timer:3500,timerProgressBar:true}); }
    function clearErrors(){ $('.invalid-feedback').text(''); $('.form-control').removeClass('is-invalid'); }

    var lastConflicts = {}; // employee_id => info

    function selectedEmployees(){ return $('.emp-check:checked').map(function(){ return this.value; }).get(); }
    function updateCount(){ $('#selected-count').text(selectedEmployees().length); }

    // Filtre + tout cocher
    $('#emp_search').on('input', function(){
        var q = this.value.toLowerCase();
        $('#emp-list .emp-item').each(function(){ $(this).toggle(($(this).data('search')||'').indexOf(q) !== -1); });
    });
    $('#check-all').on('change', function(){
        var on = this.checked;
        $('#emp-list .emp-item:visible .emp-check').prop('checked', on);
        updateCount(); checkConflicts();
    });
    $('#emp-list').on('change', '.emp-check', function(){ updateCount(); scheduleCheck(); });

    // Test temps réel des conflits
    var timer = null;
    function scheduleCheck(){ clearTimeout(timer); timer = setTimeout(checkConflicts, 250); }
    function checkConflicts(){
        var ids = selectedEmployees(), date = $('#reg_date').val();
        $('.conflict-tag').addClass('d-none');
        if (!date || ids.length === 0) { $('#conflict-panel').addClass('d-none'); lastConflicts = {}; return; }
        $.ajax({ url:R.check, type:'POST', data:{_token:CSRF, date:date, employee_ids:ids},
            success:function(r){
                lastConflicts = r.conflicts || {};
                var keys = Object.keys(lastConflicts);
                if (keys.length === 0){ $('#conflict-panel').addClass('d-none'); $('#conflict-list').empty(); return; }
                var html = '';
                keys.forEach(function(id){
                    $('#emp_'+id).closest('.emp-item').find('.conflict-tag').removeClass('d-none');
                    var opt = $('label[for="emp_'+id+'"]').text().trim().replace('· pointage existant','');
                    var c = lastConflicts[id];
                    html += '<li>'+opt+' — '+(c.check_in||'?')+(c.check_out?('–'+c.check_out):'')+' ('+(c.status||'')+')</li>';
                });
                $('#conflict-list').html(html);
                $('#conflict-panel').removeClass('d-none');
            }
        });
    }
    $('#reg_date').on('change', scheduleCheck);

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

    function setLoading(on){
        $('#submit-reg').prop('disabled', on);
        $('#submit-reg-text').toggleClass('d-none', on);
        $('#submit-reg-spinner').toggleClass('d-none', !on);
    }

    // Soumission (confirmation d'écrasement si conflits)
    $('#regForm').on('submit', function(e){
        e.preventDefault(); clearErrors();
        var ids = selectedEmployees();
        if (ids.length === 0){ toast('warning','Sélectionnez au moins un employé'); return; }

        var conflictingSelected = ids.filter(function(id){ return lastConflicts.hasOwnProperty(id); });

        var doSubmit = function(force){
            setLoading(true);
            $.ajax({ url:R.store, type:'POST',
                data:{_token:CSRF, cause_id:$('#cause_id').val(), employee_ids:ids, date:$('#reg_date').val(), check_in:$('#check_in').val(), check_out:$('#check_out').val(), force:force?1:0},
                success:function(r){ if(r.success){ toast('success', r.message); setTimeout(()=>location.reload(),1200);} else { toast('error', r.message||'Erreur'); setLoading(false);} },
                error:function(xhr){
                    setLoading(false);
                    if(xhr.status===422 && xhr.responseJSON?.errors){
                        Object.keys(xhr.responseJSON.errors).forEach(function(k){ var key=k.replace('.0',''); $('#'+key+'-error').text(xhr.responseJSON.errors[k][0]); $('#'+key).addClass('is-invalid'); });
                        toast('error','Veuillez corriger les champs.');
                    } else { toast('error', xhr.responseJSON?.message || 'Erreur'); }
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
                if(res.isConfirmed) doSubmit(true);
                else if(res.isDenied) doSubmit(false);
            });
        } else {
            doSubmit(false);
        }
    });

    // Réinitialiser à l'ouverture
    $('#regModal').on('shown.bs.modal', function(){ updateCount(); });
    $('#regModal').on('hidden.bs.modal', function(){
        clearErrors(); $('.emp-check, #check-all').prop('checked', false);
        $('.conflict-tag').addClass('d-none'); $('#conflict-panel').addClass('d-none'); updateCount(); setLoading(false);
    });
});
</script>
@endsection
