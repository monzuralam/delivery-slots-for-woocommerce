function SectionHead({ title, subtitle }) {
    return (
        <div className="dsw-gs-head">
            <h2>{title}</h2>
            {subtitle ? <p>{subtitle}</p> : null}
        </div>
    );
}

export default SectionHead;
