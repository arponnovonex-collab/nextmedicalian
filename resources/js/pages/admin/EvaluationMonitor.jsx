import React, { useState, useEffect } from 'react';
import api from '../../api/axios';
import Modal from '../../components/Modal';
import {
  FileCheck2,
  AlertCircle,
  CheckCircle,
  Clock,
  Search,
  Filter,
  Eye,
  Edit3,
  ThumbsUp,
  ThumbsDown,
  Layers,
  Building
} from 'lucide-react';

export default function EvaluationMonitor() {
  const [exams, setExams] = useState([]);
  const [selectedExamId, setSelectedExamId] = useState('');
  const [monitorData, setMonitorData] = useState(null);
  const [submittedMarks, setSubmittedMarks] = useState([]);
  const [corrections, setCorrections] = useState([]);
  const [activeTab, setActiveTab] = useState('overview'); // 'overview', 'marks', 'corrections'
  const [loading, setLoading] = useState(true);

  // Review Correction Modal
  const [selectedCorrection, setSelectedCorrection] = useState(null);
  const [reviewStatus, setReviewStatus] = useState('approved');
  const [adminNotes, setAdminNotes] = useState('');

  // Direct Admin Correction Modal
  const [isDirectCorrectModalOpen, setIsDirectCorrectModalOpen] = useState(false);
  const [directCorrectMark, setDirectCorrectMark] = useState(null);
  const [newDirectMarks, setNewDirectMarks] = useState('');
  const [directReason, setDirectReason] = useState('');

  useEffect(() => {
    fetchExams();
  }, []);

  useEffect(() => {
    if (selectedExamId) {
      fetchMonitoring();
      fetchMarksList();
      fetchCorrections();
    }
  }, [selectedExamId]);

  const fetchExams = async () => {
    try {
      const res = await api.get('/admin/exams?per_page=100');
      if (res.data.success) {
        setExams(res.data.data.data);
        if (res.data.data.data.length > 0) {
          setSelectedExamId(res.data.data.data[0].id);
        }
      }
    } catch (e) {}
  };

  const fetchMonitoring = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/admin/evaluations/monitor?exam_id=${selectedExamId}`);
      if (res.data.success) {
        setMonitorData(res.data.data);
      }
    } catch (e) {
    } finally {
      setLoading(false);
    }
  };

  const fetchMarksList = async () => {
    try {
      const res = await api.get(`/admin/evaluations/marks?exam_id=${selectedExamId}&per_page=50`);
      if (res.data.success) {
        setSubmittedMarks(res.data.data.data);
      }
    } catch (e) {}
  };

  const fetchCorrections = async () => {
    try {
      const res = await api.get('/admin/evaluations/corrections?status=pending');
      if (res.data.success) {
        setCorrections(res.data.data.data);
      }
    } catch (e) {}
  };

  const handleReviewCorrectionSubmit = async (e) => {
    e.preventDefault();
    if (!selectedCorrection) return;

    try {
      await api.post(`/admin/evaluations/corrections/${selectedCorrection.id}/review`, {
        status: reviewStatus,
        admin_notes: adminNotes,
      });
      setSelectedCorrection(null);
      setAdminNotes('');
      fetchCorrections();
      fetchMarksList();
      fetchMonitoring();
    } catch (err) {
      alert(err.response?.data?.message || 'Error processing review.');
    }
  };

  const handleOpenDirectCorrect = (mark) => {
    setDirectCorrectMark(mark);
    setNewDirectMarks(mark.final_marks);
    setDirectReason('');
    setIsDirectCorrectModalOpen(true);
  };

  const handleDirectCorrectSubmit = async (e) => {
    e.preventDefault();
    if (!directCorrectMark) return;

    try {
      await api.post('/admin/evaluations/admin-correct', {
        marks_id: directCorrectMark.id,
        new_marks: parseFloat(newDirectMarks),
        reason: directReason,
      });
      setIsDirectCorrectModalOpen(false);
      fetchMarksList();
      fetchMonitoring();
    } catch (err) {
      alert(err.response?.data?.message || 'Error executing admin correction.');
    }
  };

  const m = monitorData?.metrics || {};

  return (
    <div className="space-y-6">
      {/* Top Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Evaluation Live Monitor</h1>
          <p className="text-xs text-slate-500 mt-1">Real-time script evaluation counts, submitted marks auditing, and faculty correction requests.</p>
        </div>

        <div className="flex items-center gap-2 bg-white px-3 py-2 rounded-xl border border-slate-200 shadow-xs">
          <span className="text-xs font-semibold text-slate-600">Select Exam:</span>
          <select
            value={selectedExamId}
            onChange={(e) => setSelectedExamId(e.target.value)}
            className="text-xs font-bold text-slate-800 bg-transparent focus:outline-hidden cursor-pointer"
          >
            {exams.map(e => (
              <option key={e.id} value={e.id}>{e.code} - {e.title}</option>
            ))}
          </select>
        </div>
      </div>

      {/* Metrics Banner */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
          <span className="text-[10px] uppercase font-bold text-slate-400 block">Total Candidates</span>
          <strong className="text-2xl font-black text-slate-900">{m.total_registered || 0}</strong>
          <span className="text-[10px] text-slate-400 block mt-1">Enrolled for exam</span>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
          <span className="text-[10px] uppercase font-bold text-slate-400 block">Evaluated Scripts</span>
          <strong className="text-2xl font-black text-emerald-600">{m.total_evaluated || 0}</strong>
          <span className="text-[10px] text-emerald-600 font-semibold block mt-1">{m.completion_percentage || 0}% Completed</span>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
          <span className="text-[10px] uppercase font-bold text-slate-400 block">Pending Scripts</span>
          <strong className="text-2xl font-black text-amber-600">{m.pending_evaluations || 0}</strong>
          <span className="text-[10px] text-slate-400 block mt-1">Awaiting marks entry</span>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
          <span className="text-[10px] uppercase font-bold text-slate-400 block">Pending Corrections</span>
          <strong className="text-2xl font-black text-rose-600">{corrections.length}</strong>
          <span className="text-[10px] text-rose-600 font-semibold block mt-1">Needs review</span>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-slate-200">
        <button
          onClick={() => setActiveTab('overview')}
          className={`pb-3 px-4 text-xs font-bold uppercase tracking-wider transition-all border-b-2 ${activeTab === 'overview' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
        >
          Progress Overview
        </button>
        <button
          onClick={() => setActiveTab('marks')}
          className={`pb-3 px-4 text-xs font-bold uppercase tracking-wider transition-all border-b-2 ${activeTab === 'marks' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
        >
          Audited Marks Records ({submittedMarks.length})
        </button>
        <button
          onClick={() => setActiveTab('corrections')}
          className={`pb-3 px-4 text-xs font-bold uppercase tracking-wider transition-all border-b-2 relative ${activeTab === 'corrections' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
        >
          Correction Requests
          {corrections.length > 0 && (
            <span className="ml-2 px-1.5 py-0.5 bg-rose-500 text-white rounded-full text-[10px]">
              {corrections.length}
            </span>
          )}
        </button>
      </div>

      {/* Tab 1: Overview */}
      {activeTab === 'overview' && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Teacher Breakdown */}
          <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
            <h3 className="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
              <FileCheck2 className="w-4 h-4 text-blue-600" /> Assigned Faculty Evaluation Output
            </h3>
            <div className="space-y-3">
              {(monitorData?.teacher_progress || []).length === 0 ? (
                <div className="text-center py-8 text-xs text-slate-400">No faculty assignments recorded.</div>
              ) : (
                monitorData.teacher_progress.map(t => (
                  <div key={t.id} className="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                    <div>
                      <div className="font-bold text-slate-900">{t.name}</div>
                      <div className="text-[10px] text-slate-400 font-mono">@{t.username}</div>
                    </div>
                    <div className="text-right">
                      <strong className="text-base font-black text-slate-900">{t.evaluated_count}</strong>
                      <span className="text-[10px] text-slate-400 block">Scripts Evaluated</span>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          {/* Center Progress */}
          <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
            <h3 className="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
              <Building className="w-4 h-4 text-emerald-600" /> Center-wise Evaluation Progress
            </h3>
            <div className="space-y-3">
              {(monitorData?.center_progress || []).length === 0 ? (
                <div className="text-center py-8 text-xs text-slate-400">No centers assigned to this exam.</div>
              ) : (
                monitorData.center_progress.map(c => {
                  const pct = c.total_students > 0 ? Math.round((c.evaluated_count / c.total_students) * 100) : 0;
                  return (
                    <div key={c.id} className="p-3 rounded-xl bg-slate-50 border border-slate-100 text-xs space-y-1.5">
                      <div className="flex items-center justify-between">
                        <span className="font-bold text-slate-900">{c.name}</span>
                        <span className="font-mono text-[10px] font-bold text-slate-500">{c.code}</span>
                      </div>
                      <div className="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                        <div className="bg-emerald-500 h-full rounded-full transition-all" style={{ width: `${pct}%` }}></div>
                      </div>
                      <div className="flex justify-between text-[10px] text-slate-500 font-medium">
                        <span>{c.evaluated_count} / {c.total_students} Evaluated</span>
                        <span className="font-bold text-emerald-600">{pct}%</span>
                      </div>
                    </div>
                  );
                })
              )}
            </div>
          </div>
        </div>
      )}

      {/* Tab 2: Submitted Marks Table */}
      {activeTab === 'marks' && (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                  <th className="py-3 px-4">Candidate</th>
                  <th className="py-3 px-4">Evaluator Faculty</th>
                  <th className="py-3 px-4">Mode</th>
                  <th className="py-3 px-4">Final Marks</th>
                  <th className="py-3 px-4">Submission Time</th>
                  <th className="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                {submittedMarks.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="py-8 text-center text-slate-400">No submitted marks records found.</td>
                  </tr>
                ) : (
                  submittedMarks.map(mark => (
                    <tr key={mark.id} className="hover:bg-slate-50/60">
                      <td className="py-3.5 px-4">
                        <div className="font-bold text-slate-900">{mark.registration?.student?.user?.name}</div>
                        <div className="text-[11px] font-mono text-blue-600 font-bold">
                          Reg: {mark.registration?.student?.registration_number}
                        </div>
                      </td>
                      <td className="py-3.5 px-4">
                        <div className="font-semibold text-slate-800">{mark.evaluator?.name}</div>
                        <span className="text-[10px] font-mono text-slate-400">@{mark.evaluator?.username}</span>
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
                        <button
                          onClick={() => handleOpenDirectCorrect(mark)}
                          className="px-2.5 py-1 text-slate-600 hover:text-blue-600 hover:bg-blue-50 border border-slate-200 rounded-lg text-[11px] font-bold transition-colors"
                        >
                          Audited Correction
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Tab 3: Correction Requests */}
      {activeTab === 'corrections' && (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                  <th className="py-3 px-4">Candidate</th>
                  <th className="py-3 px-4">Requested By</th>
                  <th className="py-3 px-4">Old Marks &rarr; Proposed Marks</th>
                  <th className="py-3 px-4">Reason for Correction</th>
                  <th className="py-3 px-4 text-right">Decision</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                {corrections.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="py-8 text-center text-slate-400">No pending correction requests.</td>
                  </tr>
                ) : (
                  corrections.map(req => (
                    <tr key={req.id} className="hover:bg-slate-50/60">
                      <td className="py-3.5 px-4">
                        <div className="font-bold text-slate-900">{req.marks?.registration?.student?.user?.name}</div>
                        <div className="text-[11px] font-mono text-blue-600 font-bold">
                          Reg: {req.marks?.registration?.student?.registration_number}
                        </div>
                      </td>
                      <td className="py-3.5 px-4">
                        <div className="font-semibold text-slate-800">{req.requester?.name}</div>
                        <span className="text-[10px] text-slate-400">@{req.requester?.username}</span>
                      </td>
                      <td className="py-3.5 px-4 font-mono font-bold">
                        <span className="text-slate-400 line-through">{req.old_marks}</span>
                        <span className="mx-2 text-slate-300">&rarr;</span>
                        <span className="text-emerald-600 text-sm">{req.proposed_marks}</span>
                      </td>
                      <td className="py-3.5 px-4 max-w-xs text-slate-600">
                        {req.reason}
                      </td>
                      <td className="py-3.5 px-4 text-right">
                        <button
                          onClick={() => setSelectedCorrection(req)}
                          className="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition-colors"
                        >
                          Review Request
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Review Correction Modal */}
      <Modal
        isOpen={!!selectedCorrection}
        onClose={() => setSelectedCorrection(null)}
        title="Review Teacher Marks Correction Request"
      >
        {selectedCorrection && (
          <form onSubmit={handleReviewCorrectionSubmit} className="space-y-4">
            <div className="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1">
              <div>Student: <strong>{selectedCorrection.marks?.registration?.student?.user?.name}</strong></div>
              <div>Faculty: <strong>{selectedCorrection.requester?.name}</strong></div>
              <div>Existing Marks: <strong className="text-slate-700">{selectedCorrection.old_marks}</strong></div>
              <div>Proposed Marks: <strong className="text-emerald-600">{selectedCorrection.proposed_marks}</strong></div>
              <div className="text-slate-500 pt-1 border-t border-slate-200">
                Reason: <em>"{selectedCorrection.reason}"</em>
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Administrative Decision *
              </label>
              <div className="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  onClick={() => setReviewStatus('approved')}
                  className={`py-2 text-xs font-bold rounded-xl border transition-all ${reviewStatus === 'approved' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white border-slate-200 text-slate-700'}`}
                >
                  Approve Correction
                </button>
                <button
                  type="button"
                  onClick={() => setReviewStatus('rejected')}
                  className={`py-2 text-xs font-bold rounded-xl border transition-all ${reviewStatus === 'rejected' ? 'bg-rose-600 text-white border-rose-600 shadow-sm' : 'bg-white border-slate-200 text-slate-700'}`}
                >
                  Reject Correction
                </button>
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Admin Review Notes (Audited)
              </label>
              <textarea
                rows={2}
                value={adminNotes}
                onChange={(e) => setAdminNotes(e.target.value)}
                placeholder="Reason or notes for decision..."
                className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
              />
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
              <button
                type="button"
                onClick={() => setSelectedCorrection(null)}
                className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold"
              >
                Cancel
              </button>
              <button
                type="submit"
                className="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-700"
              >
                Submit Decision
              </button>
            </div>
          </form>
        )}
      </Modal>

      {/* Admin Direct Correction Modal */}
      <Modal
        isOpen={isDirectCorrectModalOpen}
        onClose={() => setIsDirectCorrectModalOpen(false)}
        title="Admin Audited Marks Correction"
      >
        <form onSubmit={handleDirectCorrectSubmit} className="space-y-4">
          <p className="text-xs text-slate-500">
            Override student's final marks with an audited administrative record.
          </p>

          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Corrected Marks *
            </label>
            <input
              type="number"
              step="0.01"
              required
              value={newDirectMarks}
              onChange={(e) => setNewDirectMarks(e.target.value)}
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl font-bold"
            />
          </div>

          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
              Audit Reason *
            </label>
            <textarea
              rows={2}
              required
              value={directReason}
              onChange={(e) => setDirectReason(e.target.value)}
              placeholder="Detailed reason for administrative correction..."
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl"
            />
          </div>

          <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button
              type="button"
              onClick={() => setIsDirectCorrectModalOpen(false)}
              className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-5 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-blue-700"
            >
              Apply Audited Correction
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
