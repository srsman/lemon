--
-- PostgreSQL database dump
--
--
-- Name: company; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE company (
    id integer NOT NULL,
    name character varying(36) NOT NULL,
    level integer NOT NULL,
    billing character varying(32) NOT NULL,
    concurrent integer NOT NULL,
    sound_check integer NOT NULL,
    data_filter integer NOT NULL,
    create_time timestamp without time zone NOT NULL
);


ALTER TABLE public.company OWNER TO postgres;

--
-- Name: company_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE company_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.company_id_seq OWNER TO postgres;

--
-- Name: company_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE company_id_seq OWNED BY company.id;


--
-- Name: gateway; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE gateway (
    id integer NOT NULL,
    username character varying(40) NOT NULL,
    password character varying(40) NOT NULL,
    ip_addr character varying(40) NOT NULL,
    company integer NOT NULL,
    registered integer NOT NULL
);


ALTER TABLE public.gateway OWNER TO postgres;

--
-- Name: gateway_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE gateway_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.gateway_id_seq OWNER TO postgres;

--
-- Name: gateway_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE gateway_id_seq OWNED BY gateway.id;


--
-- Name: product; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE product (
    id integer NOT NULL,
    name character varying(40) NOT NULL,
    price numeric(7,2) NOT NULL,
    inventory integer NOT NULL,
    create_time timestamp without time zone NOT NULL,
    remark text NOT NULL,
    company integer NOT NULL
);


ALTER TABLE public.product OWNER TO postgres;

--
-- Name: product_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE product_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.product_id_seq OWNER TO postgres;

--
-- Name: product_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE product_id_seq OWNED BY product.id;


--
-- Name: sounds; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE sounds (
    id integer NOT NULL,
    name character varying(40) NOT NULL,
    file character varying(40) NOT NULL,
    company integer NOT NULL,
    remark text NOT NULL,
    status integer NOT NULL,
    operator character varying(32) NOT NULL,
    ip_addr character varying(32) NOT NULL,
    create_time timestamp without time zone NOT NULL
);


ALTER TABLE public.sounds OWNER TO postgres;

--
-- Name: sounds_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE sounds_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.sounds_id_seq OWNER TO postgres;

--
-- Name: sounds_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE sounds_id_seq OWNED BY sounds.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE users (
    uid character varying(32) NOT NULL,
    name character varying(32) NOT NULL,
    password character varying(40) NOT NULL,
    type integer NOT NULL,
    company integer NOT NULL,
    status integer NOT NULL,
    callerid character varying(15) NOT NULL,
    icon character varying(8) NOT NULL,
    phone character varying(15) NOT NULL,
    web integer NOT NULL,
    calls integer NOT NULL,
    last_login timestamp without time zone NOT NULL,
    last_ipaddr character varying(32) NOT NULL,
    create_time timestamp without time zone NOT NULL
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY company ALTER COLUMN id SET DEFAULT nextval('company_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY gateway ALTER COLUMN id SET DEFAULT nextval('gateway_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY product ALTER COLUMN id SET DEFAULT nextval('product_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY sounds ALTER COLUMN id SET DEFAULT nextval('sounds_id_seq'::regclass);

--
-- Name: company_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY company
    ADD CONSTRAINT company_pkey PRIMARY KEY (id);


--
-- Name: gateway_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY gateway
    ADD CONSTRAINT gateway_pkey PRIMARY KEY (id);


--
-- Name: product_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY product
    ADD CONSTRAINT product_pkey PRIMARY KEY (id);


--
-- Name: sounds_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY sounds
    ADD CONSTRAINT sounds_pkey PRIMARY KEY (id);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (uid);


--
-- Name: fk_company; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY users
    ADD CONSTRAINT fk_company FOREIGN KEY (company) REFERENCES company(id);

--
-- Name: fk_company; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY product
    ADD CONSTRAINT fk_company FOREIGN KEY (company) REFERENCES company(id);


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

