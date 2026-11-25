import React, { useState, useEffect } from 'react';
import api from '../../api/axios';
import { Activity, Search, ShieldAlert, Clock, User } from 'lucide-react';

export default function AuditLogs() {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [selectedAction, setSelectedAction] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  useEffect(() => {
    fetchLogs();
  }, [search, selectedAction, page]);

  const fetchLogs = async () => {
    setLoading(true);
    try {
      let url = `/admin/audit-logs?page=${page}&per_page=25`;
      if (search) url += `&search=${encodeURIComponent(search)}`;
      if (selectedAction) url += `&action=${selectedAction}`;

      const res = await api.get(url);
      if (res.data.success) {
        setLogs(res.data.data.data);
        setTotalPages(res.data.data.last_page);
      }
    } catch (e) {
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Security & System Audit Trail</h1>
        <p className="text-xs text-slate-500 mt-1">Tamper-evident logs of administrative actions, marks submissions, corrections, and password resets.</p>
      </div>

      {/* Filter */}
      <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between gap-4">
        <div className="relative w-full sm:w-80">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
          <input
            type="text"
            placeholder="Search by action, user, or IP address..."
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            className="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-xl"
          />
        </div>

        <select
          value={selectedAction}
          onChange={(e) => { setSelectedAction(e.target.value); setPage(1); }}
          className="px-3 py-2 text-xs font-semibold text-slate-700 border border-slate-200 rounded-xl bg-white"
        >
          <option value="">All Action Types</option>
          <option value="user_login">User Login</option>
          <option value="marks_submitted">Marks Submitted</option>
          <option value="marks_correction_approved">Correction Approved</option>
          <option value="admin_direct_marks_correction">Admin Marks Correction</option>
          <option value="exam_created">Exam Created</option>
          <option value="exam_results_published">Results Published</option>
          <option value="student_import_executed">Student Import</option>
        </select>
      </div>

      {/* Logs Table */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[10px] font-bold">
                <th className="py-3 px-4">Timestamp</th>
                <th className="py-3 px-4">Actor</th>
                <th className="py-3 px-4">Action</th>
                <th className="py-3 px-4">IP & Device</th>
                <th className="py-3 px-4">Payload Summary</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
              {loading ? (
                <tr>
                  <td colSpan={5} className="py-8 text-center text-slate-400">Loading audit records...</td>
                </tr>
              ) : logs.length === 0 ? (
                <tr>
                  <td colSpan={5} className="py-8 text-center text-slate-400">No audit logs found.</td>
                </tr>
              ) : (
                logs.map(log => (
                  <tr key={log.id} className="hover:bg-slate-50/60">
                    <td className="py-3 px-4 text-slate-500 whitespace-nowrap">
                      {new Date(log.created_at).toLocaleString()}
                    </td>
                    <td className="py-3 px-4">
                      <div className="font-bold text-slate-900">{log.user?.name || 'System / Guest'}</div>
                      <span className="text-[10px] uppercase font-bold text-blue-600">{log.role || 'Unauthenticated'}</span>
                    </td>
                    <td className="py-3 px-4">
                      <span className="font-mono font-bold text-[11px] text-slate-800 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
                        {log.action}
                      </span>
                    </td>
                    <td className="py-3 px-4 font-mono text-[10px] text-slate-400">
                      {log.ip_address || '127.0.0.1'}
                    </td>
                    <td className="py-3 px-4 text-[11px] text-slate-600 max-w-xs truncate">
                      {log.new_values ? JSON.stringify(log.new_values) : '-'}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
