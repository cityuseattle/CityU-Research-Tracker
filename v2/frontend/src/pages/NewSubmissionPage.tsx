import { useState, useRef, useCallback, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  ChevronLeft, ChevronRight, CheckCircle2, Upload,
  X, FileText, Loader2, AlertCircle, UserPlus,
} from 'lucide-react'
import api from '../lib/axios'
import { useAuthStore } from '../stores/authStore'
import type { SubmissionType } from '../types/submissions'

// ── Constants ─────────────────────────────────────────────────────────────────

const STEPS = ['Type', 'Details', 'Authors', 'Files', 'Review & Submit'] as const

// ── Step Indicator ────────────────────────────────────────────────────────────

function StepIndicator({ step, total }: { step: number; total: number }) {
  return (
    <div className="flex items-center gap-2 mb-8">
      {STEPS.map((label, i) => {
        const n = i + 1
        const done = n < step
        const active = n === step
        return (
          <div key={n} className="flex items-center gap-2">
            <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors
              ${done ? 'bg-green-500 text-white' : active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400'}`}>
              {done ? <CheckCircle2 className="w-4 h-4" /> : n}
            </div>
            <span className={`text-sm ${active ? 'font-semibold text-gray-900' : 'text-gray-400'}`}>{label}</span>
            {i < total - 1 && <div className="w-8 h-px bg-gray-200" />}
          </div>
        )
      })}
    </div>
  )
}

// ── File Dropzone ─────────────────────────────────────────────────────────────

interface FileItem {
  file: File
  error?: string
}

function FileDropzone({
  files, onAdd, onRemove,
  allowedExts, maxSizeMb, maxFiles,
}: {
  files: FileItem[]
  onAdd: (f: FileItem[]) => void
  onRemove: (i: number) => void
  allowedExts: string[]
  maxSizeMb: number
  maxFiles: number
}) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [dragging, setDragging] = useState(false)

  const validateFile = (file: File): string | undefined => {
    const ext = file.name.split('.').pop()?.toLowerCase() ?? ''
    if (!allowedExts.includes(ext)) return `Extension .${ext} not allowed (${allowedExts.join(', ')})`
    if (file.size > maxSizeMb * 1024 * 1024) return `File exceeds ${maxSizeMb} MB limit`
    return undefined
  }

  const addFiles = (rawFiles: File[]) => {
    const remaining = maxFiles - files.length
    const toAdd = rawFiles.slice(0, remaining).map((f) => ({ file: f, error: validateFile(f) }))
    if (toAdd.length) onAdd(toAdd)
  }

  const onDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault(); setDragging(false)
    addFiles(Array.from(e.dataTransfer.files))
  }, [files, onAdd])

  return (
    <div>
      <div
        onDragOver={(e) => { e.preventDefault(); setDragging(true) }}
        onDragLeave={() => setDragging(false)}
        onDrop={onDrop}
        onClick={() => inputRef.current?.click()}
        className={`border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors
          ${dragging ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'}`}
      >
        <Upload className="w-8 h-8 text-gray-300 mx-auto mb-3" />
        <p className="text-sm font-medium text-gray-600">Drop files here or click to browse</p>
        <p className="text-xs text-gray-400 mt-1">
          Allowed: {allowedExts.join(', ')} · Max {maxSizeMb} MB · Up to {maxFiles} file{maxFiles > 1 ? 's' : ''}
        </p>
        <input
          ref={inputRef}
          type="file"
          multiple
          className="hidden"
          accept={allowedExts.map((e) => `.${e}`).join(',')}
          onChange={(e) => addFiles(Array.from(e.target.files ?? []))}
        />
      </div>
      {files.length > 0 && (
        <ul className="mt-3 space-y-2">
          {files.map((item, i) => (
            <li key={i} className={`flex items-center gap-3 p-2.5 rounded-lg border ${item.error ? 'border-red-200 bg-red-50' : 'border-gray-100 bg-gray-50'}`}>
              <FileText className={`w-4 h-4 flex-shrink-0 ${item.error ? 'text-red-400' : 'text-gray-400'}`} />
              <span className="text-sm flex-1 truncate text-gray-700">{item.file.name}</span>
              {item.error && <span className="text-xs text-red-600">{item.error}</span>}
              <button onClick={() => onRemove(i)} className="text-gray-400 hover:text-red-500">
                <X className="w-4 h-4" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

// ── Main Page ─────────────────────────────────────────────────────────────────

interface PendingAuthor {
  name: string
  email: string
  affiliation: string
}

interface FormState {
  submission_type_id: string
  title: string
  abstract: string
  change_summary: string
}

export default function NewSubmissionPage() {
  const navigate = useNavigate()
  const qc = useQueryClient()
  const user = useAuthStore((s) => s.user)

  // Redirect non-students away from this page
  useEffect(() => {
    if (user && !user.roles?.includes('student')) {
      navigate('/submissions', { replace: true })
    }
  }, [user, navigate])

  const [step, setStep] = useState(1)
  const [form, setForm] = useState<FormState>({
    submission_type_id: '', title: '', abstract: '', change_summary: '',
  })
  const [files, setFiles] = useState<FileItem[]>([])
  const [error, setError] = useState('')
  const [submissionId, setSubmissionId] = useState<string | null>(null)

  // Co-authors state
  const [coAuthors, setCoAuthors] = useState<PendingAuthor[]>([])
  const [showCoAuthorForm, setShowCoAuthorForm] = useState(false)
  const [coAuthorForm, setCoAuthorForm] = useState<PendingAuthor>({ name: '', email: '', affiliation: '' })
  const [coAuthorError, setCoAuthorError] = useState('')

  // Fetch submission types
  const { data: typesData } = useQuery<{ data: SubmissionType[] }>({
    queryKey: ['submission-types'],
    queryFn: () => api.get('/submission-types').then((r) => r.data),
  })

  const types = typesData?.data ?? []
  const selectedType = types.find((t) => t.id === form.submission_type_id)

  // Mutations
  const saveDraftMutation = useMutation({
    mutationFn: () => api.post('/submissions', {
      submission_type_id: form.submission_type_id,
      program_id: user?.program_id ?? null,
      title: form.title,
      abstract: form.abstract || null,
    }),
    onError: () => setError('Failed to save draft. Please try again.'),
  })

  const uploadMutation = useMutation({
    mutationFn: (id: string) => {
      const fd = new FormData()
      files.filter((f) => !f.error).forEach((f) => fd.append('files[]', f.file))
      if (form.change_summary) fd.append('change_summary', form.change_summary)
      return api.post(`/submissions/${id}/versions`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    },
  })

  const submitMutation = useMutation({
    mutationFn: (id: string) => api.post(`/submissions/${id}/submit`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['submissions'] })
      navigate('/submissions')
    },
    onError: () => setError('Submission failed. Please try again.'),
  })

  const setField = (k: keyof FormState, v: string) => setForm((f) => ({ ...f, [k]: v }))

  const addCoAuthor = () => {
    setCoAuthorError('')
    const name = coAuthorForm.name.trim()
    const email = coAuthorForm.email.trim().toLowerCase()
    const affiliation = coAuthorForm.affiliation.trim()
    if (!name) { setCoAuthorError('Name is required.'); return }
    if (!email || !/^[^@]+@[^@]+\.[^@]+$/.test(email)) { setCoAuthorError('A valid email is required.'); return }
    if (coAuthors.some((a) => a.email === email)) { setCoAuthorError('This email is already added.'); return }
    setCoAuthors((prev) => [...prev, { name, email, affiliation }])
    setCoAuthorForm({ name: '', email: '', affiliation: '' })
    setShowCoAuthorForm(false)
  }

  const removeCoAuthor = (email: string) => {
    setCoAuthors((prev) => prev.filter((a) => a.email !== email))
  }

  // Step navigation
  const canNext = (): boolean => {
    if (step === 1) return !!form.submission_type_id
    if (step === 2) return form.title.trim().length >= 3
    if (step === 3) return true   // Authors step is always optional
    if (step === 4) return files.some((f) => !f.error)
    return true
  }

  const next = async () => {
    setError('')
    if (step === 2) {
      // Save draft when moving from details to authors
      if (!submissionId) {
        try {
          const res = await saveDraftMutation.mutateAsync()
          const newId: string = res.data.data.id
          setSubmissionId(newId)
          setStep(3)
        } catch {
          // error already set by onError
        }
      } else {
        setStep(3)
      }
    } else if (step === 3) {
      // Post any pending co-authors, then advance to files
      if (submissionId && coAuthors.length > 0) {
        try {
          await Promise.all(
            coAuthors.map((a) =>
              api.post(`/submissions/${submissionId}/authors`, a).catch(() => {})
            )
          )
        } catch {
          // non-blocking – continue even if a co-author POST fails
        }
      }
      setStep(4)
    } else if (step === 4) {
      // Upload files
      if (submissionId) {
        try {
          await uploadMutation.mutateAsync(submissionId)
          setStep(5)
        } catch {
          setError('File upload failed. Please try again.')
        }
      }
    } else {
      setStep((s) => s + 1)
    }
  }

  const handleFinalSubmit = async (asDraft: boolean) => {
    if (!submissionId) return
    if (!asDraft) {
      await submitMutation.mutateAsync(submissionId)
    } else {
      qc.invalidateQueries({ queryKey: ['submissions'] })
      navigate('/submissions')
    }
  }

  const loading = saveDraftMutation.isPending || uploadMutation.isPending || submitMutation.isPending

  return (
    <div className="max-w-2xl mx-auto">
      {/* Back */}
      <button
        onClick={() => navigate('/submissions')}
        className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 mb-6"
      >
        <ChevronLeft className="w-4 h-4" />
        Back to submissions
      </button>

      <h1 className="text-2xl font-bold text-gray-900 mb-6">New Submission</h1>

      <StepIndicator step={step} total={STEPS.length} />

      {error && (
        <div className="mb-5 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg flex gap-2">
          <AlertCircle className="w-4 h-4 mt-0.5 flex-shrink-0" />
          {error}
        </div>
      )}

      <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">

        {/* Step 1: Submission Type */}
        {step === 1 && (
          <div className="space-y-5">
            <div>
              <label className="text-sm font-medium text-gray-700 mb-1 block">Submission Type *</label>
              <div className="grid grid-cols-1 gap-2">
                {types.map((t) => (
                  <label
                    key={t.id}
                    className={`flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition-colors
                      ${form.submission_type_id === t.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'}`}
                  >
                    <input
                      type="radio"
                      name="submission_type"
                      value={t.id}
                      checked={form.submission_type_id === t.id}
                      onChange={() => setField('submission_type_id', t.id)}
                      className="mt-0.5"
                    />
                    <div>
                      <p className="text-sm font-semibold text-gray-900">{t.label}</p>
                      {t.description && <p className="text-xs text-gray-500 mt-0.5">{t.description}</p>}
                      <p className="text-xs text-gray-400 mt-1">
                        Max {t.max_file_size_mb} MB · {t.allowed_extensions.join(', ')} · {t.max_files} file{t.max_files > 1 ? 's' : ''} max
                      </p>
                    </div>
                  </label>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* Step 2: Title + Abstract */}
        {step === 2 && (
          <div className="space-y-5">
            <div>
              <label className="text-sm font-medium text-gray-700 mb-1 block">Title *</label>
              <input
                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter submission title…"
                value={form.title}
                onChange={(e) => setField('title', e.target.value)}
                maxLength={500}
              />
              <p className="text-xs text-gray-400 mt-1">{form.title.length}/500</p>
            </div>
            <div>
              <label className="text-sm font-medium text-gray-700 mb-1 block">Abstract <span className="text-gray-400 font-normal">(optional)</span></label>
              <textarea
                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                rows={6}
                placeholder="Brief description of your research…"
                value={form.abstract}
                onChange={(e) => setField('abstract', e.target.value)}
                maxLength={10000}
              />
            </div>
          </div>
        )}

        {/* Step 3: Co-Authors */}
        {step === 3 && (
          <div className="space-y-4">
            <div>
              <h3 className="text-sm font-semibold text-gray-700">Co-Authors <span className="text-gray-400 font-normal">(optional)</span></h3>
              <p className="text-xs text-gray-500 mt-0.5 mb-3">
                Add co-authors who contributed to this submission. They will receive an email invitation to create an account.
              </p>

              {/* Added co-authors list */}
              {coAuthors.length > 0 && (
                <ul className="space-y-2 mb-3">
                  {coAuthors.map((a, _i) => (
                    <li key={a.email} className="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-gray-900 truncate">{a.name}</p>
                        <p className="text-xs text-gray-500 truncate">{a.email}{a.affiliation ? ` · ${a.affiliation}` : ''}</p>
                      </div>
                      <button
                        onClick={() => removeCoAuthor(a.email)}
                        className="text-gray-400 hover:text-red-500 flex-shrink-0"
                        title="Remove co-author"
                      >
                        <X className="w-4 h-4" />
                      </button>
                    </li>
                  ))}
                </ul>
              )}

              {/* Toggle add form */}
              {!showCoAuthorForm ? (
                <button
                  onClick={() => setShowCoAuthorForm(true)}
                  className="flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 font-medium"
                >
                  <UserPlus className="w-4 h-4" />
                  Add Co-Author
                </button>
              ) : (
                <div className="border border-gray-200 rounded-xl p-4 space-y-3">
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="text-xs font-medium text-gray-600 mb-1 block">Full Name *</label>
                      <input
                        className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g. Jane Smith"
                        value={coAuthorForm.name}
                        onChange={(e) => setCoAuthorForm((f) => ({ ...f, name: e.target.value }))}
                      />
                    </div>
                    <div>
                      <label className="text-xs font-medium text-gray-600 mb-1 block">Email *</label>
                      <input
                        type="email"
                        className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g. jane@example.com"
                        value={coAuthorForm.email}
                        onChange={(e) => setCoAuthorForm((f) => ({ ...f, email: e.target.value }))}
                      />
                    </div>
                  </div>
                  <div>
                    <label className="text-xs font-medium text-gray-600 mb-1 block">Affiliation <span className="text-gray-400">(optional)</span></label>
                    <input
                      className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="e.g. City University of Hong Kong"
                      value={coAuthorForm.affiliation}
                      onChange={(e) => setCoAuthorForm((f) => ({ ...f, affiliation: e.target.value }))}
                    />
                  </div>
                  {coAuthorError && (
                    <p className="text-xs text-red-600">{coAuthorError}</p>
                  )}
                  <div className="flex gap-2">
                    <button
                      onClick={addCoAuthor}
                      className="px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700"
                    >
                      Add
                    </button>
                    <button
                      onClick={() => { setShowCoAuthorForm(false); setCoAuthorError('') }}
                      className="px-3 py-1.5 border border-gray-200 text-gray-600 text-xs rounded-lg hover:bg-gray-50"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Step 4: File upload */}
        {step === 4 && selectedType && (
          <div className="space-y-4">
            <div>
              <h3 className="text-sm font-semibold text-gray-700 mb-1">Upload Document(s)</h3>
              <p className="text-xs text-gray-500 mb-3">
                Upload the main document for your submission. You can add more files up to the limit.
              </p>
              <FileDropzone
                files={files}
                onAdd={(newFiles) => setFiles((f) => [...f, ...newFiles])}
                onRemove={(i) => setFiles((f) => f.filter((_, idx) => idx !== i))}
                allowedExts={selectedType.allowed_extensions}
                maxSizeMb={selectedType.max_file_size_mb}
                maxFiles={selectedType.max_files}
              />
            </div>
            <div>
              <label className="text-sm font-medium text-gray-700 mb-1 block">
                Notes <span className="text-gray-400 font-normal">(optional)</span>
              </label>
              <textarea
                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                rows={2}
                placeholder="Any notes about this version…"
                value={form.change_summary}
                onChange={(e) => setField('change_summary', e.target.value)}
              />
            </div>
          </div>
        )}

        {/* Step 5: Review + Submit */}
        {step === 5 && (
          <div className="space-y-4">
            <h3 className="text-sm font-semibold text-gray-900">Review your submission</h3>
            <dl className="space-y-3">
              <div className="flex gap-4">
                <dt className="text-sm text-gray-500 w-28 flex-shrink-0">Type</dt>
                <dd className="text-sm font-medium text-gray-900">{selectedType?.label ?? '—'}</dd>
              </div>
              <div className="flex gap-4">
                <dt className="text-sm text-gray-500 w-28 flex-shrink-0">Title</dt>
                <dd className="text-sm font-medium text-gray-900">{form.title}</dd>
              </div>
              {form.abstract && (
                <div className="flex gap-4">
                  <dt className="text-sm text-gray-500 w-28 flex-shrink-0">Abstract</dt>
                  <dd className="text-sm text-gray-700 line-clamp-3">{form.abstract}</dd>
                </div>
              )}
              {coAuthors.length > 0 && (
                <div className="flex gap-4">
                  <dt className="text-sm text-gray-500 w-28 flex-shrink-0">Co-Authors</dt>
                  <dd className="text-sm text-gray-700">
                    {coAuthors.map((a) => a.name).join(', ')}
                  </dd>
                </div>
              )}
              <div className="flex gap-4">
                <dt className="text-sm text-gray-500 w-28 flex-shrink-0">Files</dt>
                <dd className="text-sm font-medium text-gray-900">
                  {files.filter((f) => !f.error).length} file(s) uploaded
                </dd>
              </div>
            </dl>
            <div className="pt-4 border-t border-gray-100">
              <p className="text-xs text-gray-500">
                Once submitted, your work will move to the review queue and cannot be edited unless revisions are requested.
                You can also save as draft to submit later.
              </p>
            </div>
          </div>
        )}
      </div>

      {/* Navigation */}
      <div className="flex items-center justify-between mt-6">
        <button
          onClick={() => (step === 1 ? navigate('/submissions') : setStep((s) => s - 1))}
          className="flex items-center gap-1.5 px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
          disabled={loading}
        >
          <ChevronLeft className="w-4 h-4" />
          {step === 1 ? 'Cancel' : 'Back'}
        </button>

        <div className="flex items-center gap-2">
          {step === 5 && (
            <button
              onClick={() => handleFinalSubmit(true)}
              disabled={loading}
              className="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50"
            >
              Save as Draft
            </button>
          )}
          {step < 5 ? (
            <button
              onClick={next}
              disabled={!canNext() || loading}
              className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
              {loading && <Loader2 className="w-4 h-4 animate-spin" />}
              Next
              <ChevronRight className="w-4 h-4" />
            </button>
          ) : (
            <button
              onClick={() => handleFinalSubmit(false)}
              disabled={loading}
              className="flex items-center gap-2 px-5 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 disabled:opacity-50"
            >
              {loading && <Loader2 className="w-4 h-4 animate-spin" />}
              Submit
              <CheckCircle2 className="w-4 h-4" />
            </button>
          )}
        </div>
      </div>
    </div>
  )
}
