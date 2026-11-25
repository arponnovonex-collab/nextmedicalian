import React from 'react';

export default function StatusBadge({ status, text }) {
  const map = {
    draft: 'bg-slate-100 text-slate-700 border-slate-300',
    registration_open: 'bg-blue-50 text-blue-700 border-blue-200',
    registration_closed: 'bg-amber-50 text-amber-700 border-amber-200',
    admit_card_published: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    evaluation_in_progress: 'bg-purple-50 text-purple-700 border-purple-200',
    evaluation_completed: 'bg-teal-50 text-teal-700 border-teal-200',
    result_published: 'bg-emerald-50 text-emerald-700 border-emerald-300',
    archived: 'bg-slate-200 text-slate-600 border-slate-300',
    active: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    inactive: 'bg-rose-50 text-rose-700 border-rose-200',
    passed: 'bg-emerald-50 text-emerald-700 border-emerald-300',
    failed: 'bg-rose-50 text-rose-700 border-rose-300',
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    approved: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    rejected: 'bg-rose-50 text-rose-700 border-rose-200',
  };

  const formattedText = text || (status ? status.replace(/_/g, ' ') : '');
  const style = map[status] || 'bg-slate-100 text-slate-700 border-slate-200';

  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider border ${style}`}>
      {formattedText}
    </span>
  );
}
