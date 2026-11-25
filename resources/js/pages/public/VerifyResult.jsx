import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import axios from 'axios';
import { CheckCircle2, ShieldAlert, Sparkles, Award, ArrowLeft } from 'lucide-react';

export default function VerifyResult() {
  const { token } = useParams();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    axios.get(`/api/public/verify/result/${token}`)
      .then(res => {
        if (res.data.success) {
          setData(res.data.data);
        } else {
          setError(true);
        }
      })
      .catch(() => setError(true))
      .finally(() => setLoading(false));
  }, [token]);

  return (
    <div className="min-h-screen bg-slate-900 flex flex-col justify-center items-center p-4">
      <div className="w-full max-w-md bg-white rounded-3xl shadow-2xl p-6 sm:p-8 space-y-6">
        <div className="text-center">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-bold uppercase tracking-widest mb-3">
            <Sparkles className="w-3.5 h-3.5" /> Official Result Verification
          </div>
          <h2 className="text-xl font-extrabold text-slate-900 tracking-tight">Verified Examination Result</h2>
          <p className="text-xs text-slate-500 mt-0.5">NEXT MEDICALIAN Central Examination Management System</p>
        </div>

        {loading ? (
          <div className="py-12 text-center">
            <div className="w-8 h-8 border-4 border-slate-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-2"></div>
            <p className="text-xs text-slate-400">Verifying official record...</p>
          </div>
        ) : error || !data ? (
          <div className="p-6 bg-rose-50 border border-rose-200 rounded-2xl text-center space-y-2">
            <ShieldAlert className="w-10 h-10 text-rose-600 mx-auto" />
            <h3 className="text-base font-bold text-rose-900">Record Not Available</h3>
            <p className="text-xs text-rose-700">Official results are either not yet released or this token is invalid.</p>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center gap-3">
              <Award className="w-8 h-8 text-emerald-600 shrink-0" />
              <div>
                <h4 className="text-xs font-bold text-emerald-900 uppercase tracking-wider">Certified Official Result</h4>
                <p className="text-[11px] text-emerald-700">Exam Board Snapshot Verified on {data.published_at}</p>
              </div>
            </div>

            <div className="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-xs space-y-2.5">
              <div className="flex justify-between">
                <span className="text-slate-500">Candidate Name:</span>
                <strong className="text-slate-900">{data.candidate_masked_name}</strong>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Registration Number:</span>
                <strong className="text-blue-600 font-mono text-sm">{data.registration_number}</strong>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Examination:</span>
                <strong className="text-slate-900 text-right">{data.examination_title}</strong>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Obtained Score:</span>
                <strong className="text-emerald-600 text-sm font-black">{data.obtained_marks} / {data.total_marks} ({data.percentage}%)</strong>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Overall Merit Rank:</span>
                <strong className="text-blue-600 text-sm font-black">#{data.overall_merit}</strong>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Qualification Status:</span>
                <strong className={`font-bold uppercase ${data.is_passed ? 'text-emerald-600' : 'text-rose-600'}`}>
                  {data.is_passed ? 'QUALIFIED (PASSED)' : 'DID NOT QUALIFY'}
                </strong>
              </div>
            </div>
          </div>
        )}

        <div className="text-center pt-2">
          <Link to="/login" className="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-800">
            <ArrowLeft className="w-3.5 h-3.5" /> Return to Sign In
          </Link>
        </div>
      </div>
    </div>
  );
}
