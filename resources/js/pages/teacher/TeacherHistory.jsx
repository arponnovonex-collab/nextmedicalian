import React, { useState, useEffect } from 'react';
import api from '../../api/axios';
import Modal from '../../components/Modal';
import { History, Search, FileEdit, CheckCircle2, Clock, AlertCircle } from 'lucide-react';

export default function TeacherHistory() {
  const [history, setHistory] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  // Correction Request Modal
  const [selectedMark, setSelectedMark] = useState(null);
  const [proposedMarks, setProposedMarks] = useState('');
  const [correctionReason, setCorrectionReason] = useState('');
  const [submittingCorrection, setSubmittingCorrection] = useState(false);

  useEffect(() => {
    fetchHistory();
  }, [search, page]);

  const fetchHistory = async () => {
    setLoading(true);
    try {
      let url = `/teacher/evaluation-history?page=${page}&per_page=20`;
      if (search) url += `&search=${encodeURIComponent(search)}`;
      const res = await api.get(url);
      if (res.data.success) {
        setHistory(res.data.data.data);
        setTotalPages(res.data.data.last_page);
      }
    } catch (e) {
    } finally {
      setLoading(false);
    }
  };

  const handleOpenCorrection = (mark) => {
    setSelectedMark(mark);
    setProposedMarks(mark.final_marks);
    setCorrectionReason('');
  };

  const handleCorrectionSubmit = async (e) => {
    e.preventDefault();
    if (!selectedMark) return;

    setSubmittingCorrection(true);
    try {
      const res = await api.post('/teacher/request-correction', {
        marks_id: selectedMark.id,
        proposed_marks: parseFloat(proposedMarks),
        reason: correctionReason,
      });

      if (res.data.success) {
        alert('Correction request submitted for administrative review.');
        setSelectedMark(null);
        fetchHistory();
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Error submitting correction request.');
    } finally {
      setSubmittingCorrection(false);
    }
  };

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Personal Evaluation History</h1>
        <p className="text-xs text-slate-500 mt-1">Audit log of all examination answer scripts personally evaluated and locked by your account.</p>
      </div>

      {/* Search Toolbar */}
      <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
        <div className="relative w-full sm:w-80">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
          <input
            type="text"
            placeholder="Search candidate by name or reg no..."
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            className="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-xl"
          />
        </div>
      </div>

      {/* History Table */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[10px] font-bold">
                <th className="py-3 px-4">Candidate Information</th>
                <th className="py-3 px-4">Examination</th>
                <th className="py-3 px-4">Submission Mode</th>
                <th className="py-3 px-4">Locked Marks</th>
                <th className="py-3 px-4">Timestamp</th>
                <th className="py-3 px-4 text-right">Correction</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
              {loading ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-400">Loading evaluation history...</td>
                </tr>
              ) : history.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-8 text-center text-slate-400">No evaluation records submitted yet.</td>
                </tr>
              ) : (
                history.map(mark => {
                  const pendingReq = (mark.correction_requests || []).find(r => r.status === 'pending');
                  return (
                    <tr key={mark.id} className="hover:bg-slate-50/60">
                      <td className="py-3.5 px-4">
                        <div className="font-bold text-slate-900">{mark.registration?.student?.user?.name}</div>
                        <div className="text-[11px] font-mono text-blue-600 font-bold">
                          Reg: {mark.registration?.student?.registration_number}
                        </div>
                      </td>
                      <td className="py-3.5 px-4">
                        <div className="font-semibold text-slate-800">{mark.registration?.exam?.title}</div>
                        <span className="text-[10px] text-slate-400 font-mono">{mark.registration?.exam?.code}</span>
                      </td>
                      <td className="py-3.5 px-4 uppercase text-[10px] font-bold text-slate-500">
                        {mark.evaluation_mode}
                      </td>
                      <td className="py-3.5 px-4 font-black text-sm text-slate-900">
                        {mark.final_marks}
                      </td>
                      <td className="py-3.5 px-4 text-slate-400 text-[11px]">
                        {new Date(mark.submitted_at).toLocaleString()}
                      </td>
                      <td className="py-3.5 px-4 text-right">
                        {pendingReq ? (
                          <span className="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-md">
                            <Clock className="w-3 h-3" /> Correction Pending Review
                          </span>
                        ) : (
                          <button
                            onClick={() => handleOpenCorrection(mark)}
                            className="inline-flex items-center gap-1 px-2.5 py-1 text-slate-600 hover:text-blue-600 hover:bg-blue-50 border border-slate-200 rounded-lg text-[11px] font-bold transition-colors"
                          >
                            <FileEdit className="w-3 h-3" /> Request Correction
                          </button>
                        )}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Correction Request Modal */}
      <Modal
        isOpen={!!selectedMark}
        onClose={() => setSelectedMark(null)}
        title="Submit Marks Correction Request"
      >
        {selectedMark && (
          <form onSubmit={handleCorrectionSubmit} className="space-y-4">
            <p className="text-xs text-slate-500">
              Submitted marks are locked. Submit a correction request with proposed marks and reason for admin review.
            </p>

            <div className="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
              <div>Candidate: <strong>{selectedMark.registration?.student?.user?.name}</strong></div>
              <div>Current Locked Score: <strong className="text-slate-900">{selectedMark.final_marks} Marks</strong></div>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Proposed Corrected Marks *
              </label>
              <input
                type="number"
                step="0.01"
                required
                value={proposedMarks}
                onChange={(e) => setProposedMarks(e.target.value)}
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl font-bold"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Reason for Correction *
              </label>
              <textarea
                rows={3}
                required
                value={correctionReason}
                onChange={(e) => setCorrectionReason(e.target.value)}
                placeholder="State the reason (e.g., misbubbled student answer key or tallying calculation error)..."
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
              <button
                type="button"
                onClick={() => setSelectedMark(null)}
                className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={submittingCorrection}
                className="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-700 disabled:opacity-50"
              >
                {submittingCorrection ? 'Submitting...' : 'Submit Request'}
              </button>
            </div>
          </form>
        )}
      </Modal>
    </div>
  );
}
