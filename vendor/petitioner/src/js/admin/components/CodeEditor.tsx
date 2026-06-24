export default function CodeEditor({ title = '', help = '', code = '' }) {
	return (
		<div>
			<div>
				<h3>{title}</h3>
				<p>{help}</p>
			</div>
			<textarea
				name="petitioner_custom_css"
				id="petitionerCode"
				rows={10}
				cols={50}
				className="large-text code petitioner-code-editor"
			>
				{code}
			</textarea>
		</div>
	);
}
